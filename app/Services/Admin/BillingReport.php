<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\Billing\UsageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Cashier\Cashier;

class BillingReport
{
    private float $deadline;

    public function owners(array $f)
    {
        $stores = app(OperationsReport::class)->stores($f);
        if ($f['store_id'] ?? null) {
            $stores->whereKey($f['store_id']);
        }

        return User::whereIn('id', $stores->select('user_id'));
    }

    public function plans(array $f): array
    {
        return Cache::remember('admin.plans.v1.'.$this->key($f), 60, function () use ($f) {
            $counts = ['accounts' => 0, 'free' => 0, 'pro' => 0, 'active' => 0, 'trialing' => 0, 'past_due' => 0, 'cancelling' => 0];
            $usage = app(UsageService::class);
            foreach ($this->owners($f)->with('subscriptions')->lazyById(200) as $owner) {
                $counts['accounts']++;
                $pro = $usage->isSubscribed($owner);
                $counts[$pro ? 'pro' : 'free']++;
                $subscription = $owner->subscription('default');
                if ($pro) {
                    $counts[$subscription->stripe_status]++;
                    if ($subscription->ends_at) {
                        $counts['cancelling']++;
                    }
                } elseif ($subscription?->stripe_status === 'past_due') {
                    $counts['past_due']++;
                }
            }

            return $counts;
        });
    }

    public function revenue(array $f): array
    {
        $secret = (string) (config('admin_operations.stripe_reporting_key') ?: config('cashier.secret'));
        $mode = preg_match('/^(sk|rk)_live_/', $secret) ? 'live' : 'test';
        // Local development must never fetch real financial data, even with a copied key.
        if (! config('admin_operations.stripe_reporting') || ! $secret || ($mode === 'live' && ! app()->environment('production'))) {
            return ['state' => 'not_connected', 'mode' => $mode];
        }

        return Cache::remember('admin.revenue.v1.'.$this->key($f).hash('sha256', $secret), 600, function () use ($f, $secret, $mode) {
            try {
                $this->deadline = microtime(true) + 12;
                $owners = $this->owners($f)->with('subscriptions')->get();
                $subscriptionIds = $owners->flatMap(fn ($u) => $u->subscriptions->where('type', 'default')->pluck('stripe_id'))->flip();
                $paid = [];
                $invoiceCount = 0;
                $cursor = null;
                // Scan paid invoices by creation order, but attribute revenue by paid_at.
                // Do not filter created >= start: older invoices can be paid this period.
                for ($page = 0; $page < 5; $page++) {
                    $params = ['status' => 'paid', 'limit' => 100, 'created' => ['lt' => $f['end']->timestamp]];
                    if ($cursor) {
                        $params['starting_after'] = $cursor;
                    }
                    $invoices = $this->get('invoices', $params, $secret);
                    foreach ($invoices['data'] as $invoice) {
                        $id = data_get($invoice, 'parent.subscription_details.subscription') ?? ($invoice['subscription'] ?? null);
                        $paidAt = data_get($invoice, 'status_transitions.paid_at');
                        if (! $id || ! $subscriptionIds->has($id) || ($invoice['livemode'] ?? null) !== ($mode === 'live') || ! $paidAt || $paidAt < $f['start']->timestamp || $paidAt >= $f['end']->timestamp) {
                            continue;
                        }
                        $currency = strtolower($invoice['currency']);
                        $paid[$currency] = ($paid[$currency] ?? 0) + (int) $invoice['amount_paid'];
                        $invoiceCount++;
                    }
                    if (! $invoices['has_more']) {
                        break;
                    }
                    $cursor = end($invoices['data'])['id'] ?? null;
                    if (! $cursor || $page === 4) {
                        return ['state' => 'too_many', 'mode' => $mode];
                    }
                }

                $mrr = [];
                $unknown = 0;
                $prices = [];
                $usage = app(UsageService::class);
                foreach ($owners as $owner) {
                    if (! $usage->isSubscribed($owner) || $owner->subscription('default')->stripe_status !== 'active') {
                        continue;
                    }
                    $sub = $owner->subscription('default');
                    $priceId = $sub->stripe_price;
                    if (! $priceId || (count($prices) >= 12 && ! isset($prices[$priceId]))) {
                        $unknown++;

                        continue;
                    }
                    try {
                        $price = $prices[$priceId] ??= $this->get('prices/'.rawurlencode($priceId), [], $secret);
                    } catch (\Illuminate\Http\Client\RequestException $e) {
                        if ($e->response->status() !== 404) {
                            throw $e;
                        }
                        // Local fixtures can refer to a price absent from this sandbox.
                        $unknown++;

                        continue;
                    }
                    $interval = data_get($price, 'recurring.interval');
                    $intervalCount = (int) data_get($price, 'recurring.interval_count', 1);
                    if (($price['livemode'] ?? null) !== ($mode === 'live') || ! isset($price['unit_amount']) || $intervalCount < 1 || ! in_array($interval, ['month', 'year'], true) || data_get($price, 'recurring.usage_type') === 'metered' || $sub->quantity === null) {
                        $unknown++;

                        continue;
                    }
                    $currency = strtolower($price['currency']);
                    $mrr[$currency] = ($mrr[$currency] ?? 0) + $price['unit_amount'] * $sub->quantity / ($intervalCount * ($interval === 'year' ? 12 : 1));
                }

                return ['state' => 'ready', 'mode' => $mode, 'paid' => $paid, 'invoices' => $invoiceCount, 'mrr' => $mrr, 'unknown' => $unknown, 'refreshed' => now()->toIso8601String()];
            } catch (\Throwable $e) {
                // Never return provider responses, customer details or credentials.
                return ['state' => 'unavailable', 'mode' => $mode];
            }
        });
    }

    private function get(string $path, array $params, string $secret): array
    {
        if (microtime(true) >= $this->deadline) {
            throw new \RuntimeException('Report time limit reached.');
        }

        return Http::withToken($secret)->withHeaders(['Stripe-Version' => Cashier::STRIPE_VERSION])
            ->acceptJson()->connectTimeout(2)->timeout(min(5, max(1, (int) ($this->deadline - microtime(true)))))->get('https://api.stripe.com/v1/'.$path, $params)->throw()->json();
    }

    private function key(array $f): string
    {
        return hash('sha256', json_encode([$f['from'], $f['to'], $f['scope'], $f['store_id'] ?? null, config('admin_operations.test_store_ids'), config('cashier.price_id'), config('billing.legacy_pro_price_ids')]));
    }
}
