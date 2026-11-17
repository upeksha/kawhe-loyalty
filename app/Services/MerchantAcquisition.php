<?php

namespace App\Services;

use App\Models\StampEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MerchantAcquisition
{
    public const CAMPAIGN = 'kawhe_nz_cafe_pilot';

    private const CREATIVES = ['paper_cards_a', 'no_new_till_b'];

    public function isPilot(?array $tags): bool
    {
        return config('ads_pilot.enabled')
            && ($tags['utm_source'] ?? null) === 'chatgpt_ads'
            && ($tags['utm_medium'] ?? null) === 'cpc'
            && ($tags['utm_campaign'] ?? null) === self::CAMPAIGN;
    }

    public function signupStarted(Request $request): void
    {
        $tags = $request->session()->get('signup_attribution');
        if ($this->isPilot($tags)) {
            try {
                $this->insert('signup_started', $this->signupSession($request), $tags);
            } catch (\Throwable) {
                Log::warning('Could not record merchant acquisition milestone', ['event' => 'signup_started']);
            }
        }
    }

    public function signupSession(Request $request): string
    {
        return $request->session()->remember('ads_pilot_signup_start', fn () => Str::uuid()->toString());
    }

    public function track(User $user, string $event, ?Request $request = null): void
    {
        try {
            $this->record($user, $event, $request);
        } catch (\Throwable) {
            Log::warning('Could not record merchant acquisition milestone', ['event' => $event, 'user_id' => $user->id]);
        }
    }

    public function record(User $user, string $event, ?Request $request = null): void
    {
        if (! $this->eligible($user)) {
            return;
        }

        $reference = $request?->input('ads_click_reference');
        $encrypted = $event === 'signup_completed' && $request?->boolean('ads_measurement_consent')
            && is_string($reference) && strlen($reference) <= 2048
            && preg_match('/\A[^\x00-\x20\x7f]+\z/', $reference)
                ? Crypt::encryptString($reference) : null;

        $this->insert($event, (string) $user->id, $user->signup_attribution, $user->id, [
            'encrypted_click_reference' => $encrypted,
        ]);
    }

    public function recordInvoice(User $user, array $invoice): void
    {
        $amount = $invoice['amount_paid'] ?? null;
        $paidAt = $invoice['status_transitions']['paid_at'] ?? null;
        $priceIds = array_filter([config('cashier.price_id'), ...config('billing.legacy_pro_price_ids', [])]);
        if (! $this->eligible($user) || ! is_int($amount) || $amount <= 0
            || ! is_int($paidAt) || $paidAt < $user->created_at->timestamp
            || ($invoice['status'] ?? null) !== 'paid' || ($invoice['currency'] ?? null) !== 'nzd'
            || ($invoice['paid_out_of_band'] ?? false) || ! is_string($invoice['id'] ?? null)) {
            return;
        }

        foreach (data_get($invoice, 'lines.data', []) as $line) {
            $price = data_get($line, 'pricing.price_details.price') ?? data_get($line, 'price.id');
            if (in_array($price, $priceIds, true)) {
                $this->insert('pro_payment', $invoice['id'], $user->signup_attribution, $user->id, [
                    'occurred_at' => now()->setTimestamp($paidAt),
                    'amount_minor' => $amount,
                    'currency' => 'NZD',
                ]);

                return;
            }
        }
    }

    public function confirmActivation(User $owner, StampEvent $stamp): bool
    {
        $publishedAt = $this->events()->where('user_id', $owner->id)->where('event', 'programme_published')->value('occurred_at');
        if (! $this->eligible($owner) || ! $this->qualified($owner->id) || ! $publishedAt
            || $stamp->type !== 'stamp' || $stamp->count < 1 || $stamp->created_at->lt($publishedAt)
            || ! $owner->stores()->whereKey($stamp->store_id)->exists()
            || ! $stamp->loyaltyAccount()->where('store_id', $stamp->store_id)->whereNotNull('loyalty_program_id')->exists()
            || DB::table('points_transactions')->where('type', 'stamp_undo')->where('metadata->stamp_event_id', $stamp->id)->exists()) {
            return false;
        }

        $this->insert('cafe_activated', (string) $owner->id, $owner->signup_attribution, $owner->id, [
            'occurred_at' => $stamp->created_at,
        ]);

        return true;
    }

    public function classify(User $user, bool $qualified): bool
    {
        if (! $this->eligible($user) || ! $this->events()->where('user_id', $user->id)->where('event', 'signup_completed')->exists()) {
            return false;
        }
        $this->record($user, $qualified ? 'qualified' : 'excluded');

        return true;
    }

    public function qualified(int $userId): bool
    {
        $events = $this->events()->where('user_id', $userId)->whereIn('event', ['qualified', 'excluded'])->pluck('event');

        return $events->contains('qualified') && ! $events->contains('excluded');
    }

    public function events(): \Illuminate\Database\Query\Builder
    {
        return DB::table('merchant_acquisition_events');
    }

    public function sendVerifiedRegistration(int $userId, bool $validateOnly): bool
    {
        $row = $this->events()->where('user_id', $userId)->where('event', 'signup_completed')->first();
        $verifiedAt = $this->events()->where('user_id', $userId)->where('event', 'email_verified')->value('occurred_at');
        $verifiedAt = $verifiedAt ? CarbonImmutable::parse($verifiedAt) : null;
        $user = User::find($userId);
        if (! $user || ! $this->eligible($user) || ! $this->qualified($userId)
            || ! $row?->encrypted_click_reference || $row->sent_at
            || ! $user->hasVerifiedEmail() || ! $verifiedAt || $verifiedAt->lt(now()->subDays(6))
            || $verifiedAt->gt(now()->addMinutes(5))
            || now()->subDays(30)->gt($row->occurred_at)
            || ! config('ads_pilot.pixel_id') || ! config('ads_pilot.conversion_key')) {
            return false;
        }

        return Cache::lock('ads-registration:'.$row->event_id, 30)->get(function () use ($row, $user, $verifiedAt, $validateOnly) {
            if ($this->events()->where('event_id', $row->event_id)->whereNotNull('sent_at')->exists() || ! $this->qualified($user->id)) {
                return false;
            }

            $response = Http::withToken(config('ads_pilot.conversion_key'))->timeout(8)
                ->withQueryParameters(['pid' => config('ads_pilot.pixel_id')])
                ->post('https://bzr.openai.com/v1/events', [
                    'validate_only' => $validateOnly,
                    'events' => [[
                        'id' => $row->event_id,
                        'type' => 'registration_completed',
                        'timestamp_ms' => $verifiedAt->timestamp * 1000,
                        'oppref' => Crypt::decryptString($row->encrypted_click_reference),
                        'source_url' => 'https://app.kawhe.shop/register',
                        'action_source' => 'web',
                        'opt_out' => true,
                        'data' => ['type' => 'customer_action'],
                    ]],
                ]);
            if (! $response->successful()) {
                return false;
            }
            if (! $validateOnly) {
                $this->events()->where('event_id', $row->event_id)->update([
                    'sent_at' => now(), 'encrypted_click_reference' => null,
                ]);
            }

            return true;
        }) === true;
    }

    private function eligible(User $user): bool
    {
        return $this->isPilot($user->signup_attribution) && ! $user->isSuperAdmin()
            && ! $user->is_staff_account && $user->stores()->exists();
    }

    private function insert(string $event, string $identity, array $tags, ?int $userId = null, array $fields = []): void
    {
        $creative = $tags['utm_content'] ?? null;
        $key = hash_hmac('sha256', $event.':'.$identity, (string) config('app.key'));
        $this->events()->insertOrIgnore([
            'event_id' => Str::uuid()->toString(),
            'dedupe_key' => $key,
            'user_id' => $userId,
            'event' => $event,
            'creative' => in_array($creative, self::CREATIVES, true) ? $creative : null,
            'occurred_at' => now(),
            ...$fields,
        ]);
    }
}
