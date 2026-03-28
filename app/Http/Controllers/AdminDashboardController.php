<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\SupportAuditLog;
use App\Models\User;
use App\Models\StampEvent;
use Illuminate\Http\Request;
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

        return view('admin.dashboard', compact('stats', 'recent_stores', 'recent_stamps', 'recent_support_events', 'merchant_issue_diagnostics'));
    }
}
