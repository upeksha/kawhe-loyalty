<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use App\Models\StampEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantActivityController extends Controller
{
    public function index(Request $request): View
    {
        $events = StampEvent::query()
            ->with(['loyaltyAccount.customer', 'store', 'user'])
            ->whereIn('store_id', $request->user()->stores()->select('id'))
            ->whereIn('type', ['stamp', 'redeem', 'stamp_undo'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(25);

        return view('merchant.activity.index', compact('events'));
    }

    public function export(Request $request)
    {
        $stores = $request->user()->stores()->get(['id', 'name']);
        $storeIds = $stores->pluck('id');
        $monthStart = now()->startOfMonth();
        $nextMonth = $monthStart->copy()->addMonth();

        $joins = LoyaltyAccount::query()
            ->selectRaw('store_id, DATE(created_at) as day, COUNT(*) as total')
            ->whereIn('store_id', $storeIds)
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $nextMonth)
            ->groupBy('store_id', 'day')
            ->get();

        $events = StampEvent::query()
            ->selectRaw('store_id, DATE(created_at) as day, type, COALESCE(SUM(count), 0) as total')
            ->whereIn('store_id', $storeIds)
            ->whereIn('type', ['stamp', 'redeem'])
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $nextMonth)
            ->groupBy('store_id', 'day', 'type')
            ->get();

        $rows = [];
        foreach ($joins as $join) {
            $key = $join->store_id.'|'.$join->day;
            $rows[$key] = ['store_id' => $join->store_id, 'day' => $join->day, 'joins' => (int) $join->total, 'stamps' => 0, 'redeems' => 0];
        }
        foreach ($events as $event) {
            $key = $event->store_id.'|'.$event->day;
            $rows[$key] ??= ['store_id' => $event->store_id, 'day' => $event->day, 'joins' => 0, 'stamps' => 0, 'redeems' => 0];
            $rows[$key][$event->type === 'stamp' ? 'stamps' : 'redeems'] = (int) $event->total;
        }
        ksort($rows);

        return response()->streamDownload(function () use ($rows, $stores) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Store', 'New customer cards', 'Stamps added', 'Rewards redeemed']);
            foreach ($rows as $row) {
                $name = (string) ($stores->firstWhere('id', $row['store_id'])?->name ?? 'Store unavailable');
                // Prevent spreadsheet formulas from running when an owner opens their CSV.
                if (preg_match('/^[\s]*[=+@\-]/u', $name)) {
                    $name = "'".$name;
                }
                fputcsv($handle, [$row['day'], $name, $row['joins'], $row['stamps'], $row['redeems']]);
            }
            fclose($handle);
        }, 'kawhe-activity-'.$monthStart->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
