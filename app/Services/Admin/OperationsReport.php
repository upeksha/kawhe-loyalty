<?php

namespace App\Services\Admin;

use App\Models\LoyaltyAccount;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\SupportAuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationsReport
{
    public const ISSUE_STATUSES = ['failed', 'error', 'warning', 'partial', 'blocked', 'not_configured', 'degraded'];

    public function filters(Request $request): array
    {
        $data = $request->validate([
            'range' => ['nullable', 'in:today,7d,30d,90d'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'scope' => ['nullable', 'in:customers,all,test'], 'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,archived,all'],
            'type' => ['nullable', 'in:stamp,redeem,stamp_undo'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ]);
        $today = CarbonImmutable::now('Pacific/Auckland')->startOfDay();
        $range = $data['range'] ?? '7d';
        $end = filled($data['to'] ?? null) ? CarbonImmutable::parse($data['to'], 'Pacific/Auckland') : $today;
        $start = filled($data['from'] ?? null) ? CarbonImmutable::parse($data['from'], 'Pacific/Auckland') : $end->subDays(match ($range) {
            'today' => 0, '30d' => 29, '90d' => 89, default => 6
        });
        if ($end->lt($start) || $start->diffInDays($end) > 89 || $end->gt($today)) {
            throw ValidationException::withMessages(['from' => 'Choose a period of up to 90 days ending today or earlier.']);
        }

        return array_replace($data, [
            'range' => $range, 'scope' => $data['scope'] ?? 'customers', 'q' => $data['q'] ?? '', 'status' => $data['status'] ?? 'active', 'type' => $data['type'] ?? '', 'store_id' => $data['store_id'] ?? null,
            'start' => $start->utc(), 'end' => $end->addDay()->utc(),
            'from' => $start->toDateString(), 'to' => $end->toDateString(),
        ]);
    }

    public function stores(array $f)
    {
        $q = Store::withTrashed();
        $ids = config('admin_operations.test_store_ids', []);
        if ($f['scope'] === 'test') {
            $q->whereIn('id', $ids);
        } elseif ($f['scope'] === 'customers' && $ids) {
            $q->whereNotIn('id', $ids);
        }

        return $q;
    }

    public function events(array $f)
    {
        return StampEvent::whereIn('store_id', $this->stores($f)->select('id'))
            ->where('created_at', '>=', $f['start'])->where('created_at', '<', $f['end'])
            ->whereIn('type', ['stamp', 'redeem', 'stamp_undo']);
    }

    public function accounts(array $f)
    {
        return LoyaltyAccount::whereIn('store_id', $this->stores($f)->select('id'));
    }

    public function issues(array $f)
    {
        return SupportAuditLog::select(['id', 'store_id', 'event_type', 'status', 'created_at'])
            ->with('store:id,name,deleted_at')
            ->where(fn ($q) => $q->whereIn('store_id', $this->stores($f)->select('id'))->orWhereNull('store_id'))
            ->whereIn('status', self::ISSUE_STATUSES)
            ->where('created_at', '>=', $f['start'])->where('created_at', '<', $f['end']);
    }

    public function summary(array $f): array
    {
        $key = 'admin.operations.v1.'.hash('sha256', json_encode([$f['start']->toIso8601String(), $f['end']->toIso8601String(), $f['scope'], config('admin_operations.test_store_ids')]));

        return Cache::remember($key, 60, function () use ($f) {
            $types = $this->events($f)->selectRaw('type, SUM(count) as quantity')->groupBy('type')->pluck('quantity', 'type');
            $stores = $this->stores($f)->whereNull('deleted_at');
            $accounts = $this->accounts($f);

            return [
                'stores' => (clone $stores)->count(),
                'trading' => $this->events($f)->distinct()->count('store_id'),
                'programmes' => DB::table('loyalty_programs')->whereIn('store_id', $this->stores($f)->select('id'))->whereNull('deleted_at')->count(),
                'customers' => (clone $accounts)->distinct()->count('customer_id'),
                'cards' => (clone $accounts)->count(),
                'new_cards' => (clone $accounts)->where('created_at', '>=', $f['start'])->where('created_at', '<', $f['end'])->count(),
                'stamps' => (int) ($types['stamp'] ?? 0), 'undos' => (int) ($types['stamp_undo'] ?? 0),
                'net' => (int) ($types['stamp'] ?? 0) - (int) ($types['stamp_undo'] ?? 0),
                'redeems' => (int) ($types['redeem'] ?? 0),
                'rewards_ready' => (int) (clone $accounts)->sum('reward_balance'),
                'incomplete' => (clone $stores)->whereNotNull('onboarding_step')->whereNull('onboarding_completed_at')->count(),
                'unverified' => (clone $stores)->whereHas('user', fn ($q) => $q->whereNull('email_verified_at'))->distinct()->count('user_id'),
                'issues' => $this->issues($f)->count(), 'refreshed' => now()->toIso8601String(),
                'trend' => $this->trend($f),
            ];
        });
    }

    private function trend(array $f): array
    {
        // Group into UTC hours first, then use NZ dates, including DST boundaries.
        $bucket = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d %H:00:00', created_at)",
            'pgsql' => "date_trunc('hour', created_at)",
            default => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
        };
        $days = [];
        for ($day = $f['start']->setTimezone('Pacific/Auckland'); $day->utc()->lt($f['end']); $day = $day->addDay()) {
            $days[$day->toDateString()] = ['date' => $day->toDateString(), 'stamp' => 0, 'stamp_undo' => 0, 'redeem' => 0];
        }
        foreach ($this->events($f)->selectRaw("$bucket as bucket, type, SUM(count) as quantity")->groupBy('bucket', 'type')->get() as $row) {
            $date = CarbonImmutable::parse($row->bucket, 'UTC')->setTimezone('Pacific/Auckland')->toDateString();
            if (isset($days[$date])) {
                $days[$date][$row->type] += (int) $row->quantity;
            }
        }

        return array_values($days);
    }

    public function decorateStores($stores, array $f): void
    {
        $totals = $this->events($f)->whereIn('store_id', $stores->modelKeys())
            ->selectRaw('store_id, type, SUM(count) as quantity')->groupBy('store_id', 'type')->get()->groupBy('store_id');
        foreach ($stores as $store) {
            $types = ($totals[$store->id] ?? collect())->pluck('quantity', 'type');
            $store->setAttribute('ops_net', (int) ($types['stamp'] ?? 0) - (int) ($types['stamp_undo'] ?? 0));
            $store->setAttribute('ops_redeems', (int) ($types['redeem'] ?? 0));
        }
    }

    public function queueSnapshot(): array
    {
        // Only aggregate operational counters: never read or return job payloads.
        return [
            'pending' => DB::table('jobs')->count(),
            'oldest' => DB::table('jobs')->min('created_at'),
            'failed' => DB::table('failed_jobs')->count(),
            'queues' => DB::table('failed_jobs')->select('queue')->selectRaw('COUNT(*) as total, MAX(failed_at) as last_failed')->groupBy('queue')->get(),
        ];
    }

    public function issueLabel(string $type): string
    {
        return match ($type) {
            'wallet_sync', 'program_wallet_refresh' => 'Wallet update needs review',
            'billing_issue' => 'Billing needs review',
            default => 'Recorded support event',
        };
    }
}
