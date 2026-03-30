<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\SupportAuditLog;
use App\Models\StampEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'total_stores' => Store::count(),
            'total_stamps_today' => StampEvent::whereDate('created_at', today())->count(),
            'support_events_last_7_days' => SupportAuditLog::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        $recent_stores = Store::with('user')->latest()->take(10)->get();
        $recent_stamps = StampEvent::with(['loyaltyAccount.customer', 'store'])->latest()->take(20)->get();
        $recent_support_events = SupportAuditLog::with(['actor', 'store', 'loyaltyAccount.customer'])
            ->latest()
            ->take(20)
            ->get();
        $activityTrend = $this->buildActivityTrend();
        $storeTrend = $this->buildStoreTrend();

        $merchant_issue_diagnostics = SupportAuditLog::query()
            ->select('stores.id', 'stores.name', 'users.email')
            ->selectRaw("SUM(CASE WHEN support_audit_logs.event_type = 'billing_issue' THEN 1 ELSE 0 END) as billing_issue_count")
            ->selectRaw("SUM(CASE WHEN support_audit_logs.event_type = 'wallet_sync' AND support_audit_logs.status != 'success' THEN 1 ELSE 0 END) as wallet_issue_count")
            ->join('stores', 'stores.id', '=', 'support_audit_logs.store_id')
            ->join('users', 'users.id', '=', 'stores.user_id')
            ->where('support_audit_logs.created_at', '>=', now()->subDays(14))
            ->groupBy('stores.id', 'stores.name', 'users.email')
            ->havingRaw("SUM(CASE WHEN support_audit_logs.event_type = 'billing_issue' THEN 1 ELSE 0 END) > 0
                OR SUM(CASE WHEN support_audit_logs.event_type = 'wallet_sync' AND support_audit_logs.status != 'success' THEN 1 ELSE 0 END) > 0")
            ->orderByDesc(DB::raw("SUM(CASE WHEN support_audit_logs.event_type = 'billing_issue' THEN 1 ELSE 0 END)
                + SUM(CASE WHEN support_audit_logs.event_type = 'wallet_sync' AND support_audit_logs.status != 'success' THEN 1 ELSE 0 END)"))
            ->limit(15)
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'recent_stores',
            'recent_stamps',
            'recent_support_events',
            'merchant_issue_diagnostics',
            'activityTrend',
            'storeTrend'
        ));
    }

    protected function buildActivityTrend(): array
    {
        $start = now()->subDays(13)->startOfDay();
        $days = $this->emptyTrendMap($start, 14);

        $joinsByDay = LoyaltyAccount::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('total', 'day');

        $stampsByDay = StampEvent::query()
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(count), 0) as total')
            ->where('type', 'stamp')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('total', 'day');

        $redeemsByDay = StampEvent::query()
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(count), 0) as total')
            ->where('type', 'redeem')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('total', 'day');

        $points = $days->map(function (string $day) use ($joinsByDay, $stampsByDay, $redeemsByDay) {
            return [
                'day' => $day,
                'label' => Carbon::parse($day)->format('d/m'),
                'joins' => (int) ($joinsByDay[$day] ?? 0),
                'stamps' => (int) ($stampsByDay[$day] ?? 0),
                'redeems' => (int) ($redeemsByDay[$day] ?? 0),
            ];
        })->values();

        return [
            'points' => $points,
            'joins_total' => $points->sum('joins'),
            'stamps_total' => $points->sum('stamps'),
            'redeems_total' => $points->sum('redeems'),
            'joins_chart' => $this->buildChartSeries($points->pluck('joins')->all(), 780, 240),
            'stamps_chart' => $this->buildChartSeries($points->pluck('stamps')->all(), 780, 240),
            'redeems_chart' => $this->buildChartSeries($points->pluck('redeems')->all(), 780, 240),
        ];
    }

    protected function buildStoreTrend(): array
    {
        $start = now()->subDays(13)->startOfDay();
        $days = $this->emptyTrendMap($start, 14);

        $storesByDay = Store::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('total', 'day');

        $issuesByDay = SupportAuditLog::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->where(function ($query) {
                $query->where('event_type', 'billing_issue')
                    ->orWhere(function ($wallet) {
                        $wallet->where('event_type', 'wallet_sync')
                            ->where('status', '!=', 'success');
                    });
            })
            ->groupBy('day')
            ->pluck('total', 'day');

        $points = $days->map(function (string $day) use ($storesByDay, $issuesByDay) {
            return [
                'day' => $day,
                'label' => Carbon::parse($day)->format('d/m'),
                'stores' => (int) ($storesByDay[$day] ?? 0),
                'issues' => (int) ($issuesByDay[$day] ?? 0),
            ];
        })->values();

        return [
            'points' => $points,
            'stores_total' => $points->sum('stores'),
            'issues_total' => $points->sum('issues'),
            'stores_chart' => $this->buildChartSeries($points->pluck('stores')->all(), 420, 240),
            'issues_chart' => $this->buildChartSeries($points->pluck('issues')->all(), 420, 240),
        ];
    }

    protected function emptyTrendMap(Carbon $start, int $days): Collection
    {
        return collect(range(0, $days - 1))
            ->map(fn (int $offset) => $start->copy()->addDays($offset)->toDateString());
    }

    protected function buildChartSeries(array $values, int $width, int $height): array
    {
        $count = count($values);

        if ($count === 0) {
            return [
                'line' => '',
                'area' => '',
                'max' => 0,
            ];
        }

        $max = max(max($values), 1);
        $stepX = $count > 1 ? $width / ($count - 1) : $width;
        $baseline = $height;
        $points = [];

        foreach ($values as $index => $value) {
            $x = round($index * $stepX, 2);
            $y = round($height - (($value / $max) * ($height - 18)) - 9, 2);
            $points[] = [$x, $y];
        }

        $line = collect($points)
            ->map(fn (array $point, int $index) => ($index === 0 ? 'M' : 'L') . $point[0] . ' ' . $point[1])
            ->implode(' ');

        $area = $line . ' L ' . $points[$count - 1][0] . ' ' . $baseline . ' L 0 ' . $baseline . ' Z';

        return [
            'line' => $line,
            'area' => $area,
            'max' => $max,
        ];
    }
}
