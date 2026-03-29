<?php

namespace App\Http\Controllers;

use App\Models\Store;
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

        if ($request->filled('q')) {
            $search = trim($request->string('q')->toString());
            $normalized = strtoupper($search);

            $query->where(function ($query) use ($search, $normalized) {
                $query->whereHas('loyaltyAccount.customer', function ($query) use ($search) {
                    $query->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                })->orWhereHas('loyaltyAccount', function ($query) use ($search, $normalized) {
                    $query->where('public_token', 'like', "%{$search}%")
                        ->orWhere('manual_entry_code', $normalized);
                });
            });
        }

        $summaryQuery = clone $query;
        $logs = $query->paginate(25)->withQueryString();

        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'failed' => (clone $summaryQuery)->where('status', 'failed')->count(),
            'actionable' => (clone $summaryQuery)->whereIn('status', ['failed', 'blocked', 'partial'])->count(),
        ];

        return view('merchant.support.index', [
            'logs' => $logs,
            'stores' => $stores,
            'eventType' => $request->input('event_type'),
            'status' => $request->input('status'),
            'activeStoreId' => $request->input('store_id'),
            'search' => $request->input('q'),
            'summary' => $summary,
        ]);
    }

    public function adminIndex(Request $request)
    {
        $stores = Store::withTrashed()->orderBy('name')->get(['id', 'name', 'deleted_at']);
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

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->integer('store_id'));
        }

        if ($request->filled('q')) {
            $search = trim($request->string('q')->toString());
            $normalized = strtoupper($search);

            $query->where(function ($query) use ($search, $normalized) {
                $query->whereHas('store', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                })->orWhereHas('actor', function ($query) use ($search) {
                    $query->where('email', 'like', "%{$search}%");
                })->orWhereHas('loyaltyAccount.customer', function ($query) use ($search) {
                    $query->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                })->orWhereHas('loyaltyAccount', function ($query) use ($search, $normalized) {
                    $query->where('public_token', 'like', "%{$search}%")
                        ->orWhere('manual_entry_code', $normalized);
                });
            });
        }

        $summaryQuery = clone $query;
        $logs = $query->paginate(40)->withQueryString();

        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'failed' => (clone $summaryQuery)->where('status', 'failed')->count(),
            'wallet_issues' => (clone $summaryQuery)->where('event_type', 'wallet_sync')->where('status', '!=', 'success')->count(),
            'billing_issues' => (clone $summaryQuery)->where('event_type', 'billing_issue')->count(),
        ];

        return view('admin.support.index', [
            'logs' => $logs,
            'stores' => $stores,
            'eventType' => $request->input('event_type'),
            'status' => $request->input('status'),
            'issuesOnly' => $request->boolean('issues_only'),
            'activeStoreId' => $request->input('store_id'),
            'search' => $request->input('q'),
            'summary' => $summary,
        ]);
    }
}
