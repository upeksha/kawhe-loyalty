<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantLaunchChecklistController extends Controller
{
    public function show(Request $request, Store $store): View|RedirectResponse
    {
        abort_unless($store->user_id === $request->user()->id && ! $store->trashed(), 403);

        if (! $store->onboarding_completed_at) {
            return redirect()->route('merchant.onboarding.wizard.index');
        }
        if (! $store->launch_poster_downloaded_at) {
            return redirect()->to(route('merchant.stores.qr', $store).'#download-poster');
        }

        return view('merchant.onboarding.launch', [
            'store' => $store,
            'joinUrl' => $store->resolvedDefaultProgram()?->join_url ?? $store->join_url,
        ]);
    }

    public function confirmAppInstalled(Request $request, Store $store): RedirectResponse
    {
        abort_unless($store->user_id === $request->user()->id && ! $store->trashed(), 403);

        if (! $store->onboarding_completed_at) {
            return redirect()->route('merchant.onboarding.wizard.index');
        }
        if (! $store->launch_poster_downloaded_at) {
            return redirect()->route('merchant.stores.qr', $store)
                ->withErrors(['poster' => 'Download your join poster before confirming the app installation.']);
        }

        if (! $store->launch_merchant_app_installed_at) {
            $store->forceFill(['launch_merchant_app_installed_at' => now()])->save();
        }

        return back()->with('success', 'Kawhe Merchant installation marked complete.');
    }
}
