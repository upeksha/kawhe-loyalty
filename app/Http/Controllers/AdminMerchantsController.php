<?php

namespace App\Http\Controllers;

use App\Models\MerchantSignupAlert;
use App\Models\User;
use App\Services\Billing\UsageService;
use Illuminate\Http\Request;

class AdminMerchantsController extends Controller
{
    public function index(Request $request, UsageService $usage)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'verification' => ['nullable', 'in:verified,pending'],
            'setup' => ['nullable', 'in:ready,incomplete'],
            'campaign' => ['nullable', 'string', 'max:120'],
        ]);
        // Staff and dedicated platform admins are not café acquisitions.
        $base = User::where('is_super_admin', false)->where(fn ($q) => $q->where('is_staff_account', false)->orWhereNull('is_staff_account'))->whereHas('stores');
        $query = clone $base;
        if ($search = trim($filters['q'] ?? '')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('stores', fn ($s) => $s->where('name', 'like', "%{$search}%")));
        }
        if (($filters['verification'] ?? '') === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif (($filters['verification'] ?? '') === 'pending') {
            $query->whereNull('email_verified_at');
        }
        if (($filters['setup'] ?? '') === 'ready') {
            $query->whereHas('stores', fn ($s) => $s->whereNotNull('onboarding_completed_at'));
        } elseif (($filters['setup'] ?? '') === 'incomplete') {
            $query->whereDoesntHave('stores', fn ($s) => $s->whereNotNull('onboarding_completed_at'));
        }
        if ($campaign = trim($filters['campaign'] ?? '')) {
            $query->where('signup_attribution->utm_campaign', $campaign);
        }
        $merchants = $query->with(['stores' => fn ($s) => $s->orderBy('id'), 'subscriptions'])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString();
        $merchants->getCollection()->each(fn ($user) => $user->setAttribute('admin_plan_label', $usage->planLabel($user)));
        $today = now('Pacific/Auckland')->startOfDay()->utc();
        $week = now('Pacific/Auckland')->subDays(6)->startOfDay()->utc();
        $stats = [
            'today' => (clone $base)->where('created_at', '>=', $today)->count(),
            'week' => (clone $base)->where('created_at', '>=', $week)->count(),
            'pending' => (clone $base)->whereNull('email_verified_at')->count(),
            'ready' => (clone $base)->whereHas('stores', fn ($s) => $s->whereNotNull('onboarding_completed_at'))->count(),
            'alerts_pending' => MerchantSignupAlert::whereNull('sent_at')->where('attempts', '<', 3)->count(),
            'alerts_failed' => MerchantSignupAlert::whereNull('sent_at')->where('attempts', '>=', 3)->count(),
        ];

        return response()->view('admin.merchants', compact('merchants', 'filters', 'stats'))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'same-origin');
    }
}
