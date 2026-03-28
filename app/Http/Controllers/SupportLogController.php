<?php

namespace App\Http\Controllers;

use App\Models\SupportAuditLog;
use Illuminate\Http\Request;

class SupportLogController extends Controller
{
    public function merchantIndex(Request $request)
    {
        $stores = $request->user()->stores()->orderBy('name')->get(['id', 'name']);
        $storeIds = $stores->pluck('id');

        $query = SupportAuditLog::with(['actor', 'store', 'loyaltyAccount.customer'])
            ->whereIn('store_id', $storeIds)
            ->latest();

        if ($request->filled('store_id') && $storeIds->contains((int) $request->store_id)) {
            $query->where('store_id', $request->integer('store_id'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->string('event_type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('merchant.support.index', [
            'logs' => $logs,
            'stores' => $stores,
            'eventType' => $request->input('event_type'),
            'status' => $request->input('status'),
            'activeStoreId' => $request->input('store_id'),
        ]);
    }

    public function adminIndex(Request $request)
    {
        $query = SupportAuditLog::with(['actor', 'store', 'loyaltyAccount.customer'])
            ->latest();

        if ($request->boolean('issues_only')) {
            $query->where(function ($query) {
                $query->where('event_type', 'billing_issue')
                    ->orWhere(function ($query) {
                        $query->where('event_type', 'wallet_sync')
                            ->where('status', '!=', 'success');
                    });
            });
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->string('event_type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $logs = $query->paginate(40)->withQueryString();

        return view('admin.support.index', [
            'logs' => $logs,
            'eventType' => $request->input('event_type'),
            'status' => $request->input('status'),
            'issuesOnly' => $request->boolean('issues_only'),
        ]);
    }
}
