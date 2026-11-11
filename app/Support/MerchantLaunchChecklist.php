<?php

namespace App\Support;

use App\Http\Controllers\MerchantOnboardingWizardController;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\User;

class MerchantLaunchChecklist
{
    public function forUser(?User $user, ?Store $preferredStore = null): ?array
    {
        if (! $user || $user->is_staff_account || $user->is_super_admin) {
            return null;
        }

        $stores = $user->stores()->orderBy('id')->get();
        $checklists = $stores->map(fn (Store $store) => $this->forStore($user, $store));
        $checklist = $checklists->first(fn (array $item) => $preferredStore && $item['store']?->id === $preferredStore->id)
            ?? $checklists->first(fn (array $item) => ! $item['ready'])
            ?? $checklists->first()
            ?? $this->forStore($user, null);

        return $checklist;
    }

    private function forStore(User $user, ?Store $store): array
    {
        $verified = $user->hasVerifiedEmail();
        $details = $store && $store->onboarding_step !== MerchantOnboardingWizardController::STEP_STORE_BASICS;
        $card = $store && (in_array($store->onboarding_step, [Store::ONBOARDING_STEP_CARD_READY, Store::ONBOARDING_STEP_CONTINUE_TRIAL], true)
            || $store->onboarding_completed_at !== null
            || ($store->onboarding_step === null && $store->resolvedDefaultProgram() !== null));
        $poster = $store?->launch_poster_downloaded_at !== null;
        $installed = $store?->launch_merchant_app_installed_at !== null;
        $stamped = $store && StampEvent::query()->where('store_id', $store->id)->where('type', 'stamp')->where('count', '>', 0)->exists();

        $steps = [
            ['key' => 'email', 'label' => 'Confirm your email', 'done' => $verified],
            ['key' => 'details', 'label' => 'Add café details', 'done' => (bool) $details],
            ['key' => 'card', 'label' => 'Create your card', 'done' => (bool) $card],
            ['key' => 'poster', 'label' => 'Download your join poster', 'done' => $poster],
            ['key' => 'app', 'label' => 'Install Kawhe Merchant', 'done' => $installed],
            ['key' => 'stamp', 'label' => 'Complete a test stamp', 'done' => (bool) $stamped],
        ];
        $doneCount = collect($steps)->where('done', true)->count();
        $next = collect($steps)->firstWhere('done', false);

        $nextUrl = match ($next['key'] ?? null) {
            'email' => route('verification.notice').'#verification-instructions',
            'details' => route('merchant.onboarding.wizard.store-basics'),
            'card' => $this->cardSetupUrl($store),
            'poster' => $store?->onboarding_completed_at
                ? route('merchant.stores.qr', $store).'#download-poster'
                : route('merchant.onboarding.wizard.card-ready').'#share-title',
            'app' => $store?->onboarding_completed_at
                ? route('merchant.onboarding.launch.show', $store).'#merchant-app'
                : route('merchant.onboarding.wizard.card-ready'),
            'stamp' => $store?->onboarding_completed_at
                ? route('merchant.onboarding.launch.show', $store).'#test-stamp'
                : route('merchant.onboarding.wizard.card-ready'),
            default => route('merchant.dashboard'),
        };

        return compact('store', 'steps', 'doneCount', 'next', 'nextUrl') + ['ready' => $doneCount === count($steps)];
    }

    private function cardSetupUrl(?Store $store): string
    {
        return match ($store?->onboarding_step) {
            Store::ONBOARDING_STEP_CUSTOMER_FORM => route('merchant.onboarding.wizard.customer-form'),
            Store::ONBOARDING_STEP_CARD_READY, Store::ONBOARDING_STEP_CONTINUE_TRIAL => route('merchant.onboarding.wizard.card-ready'),
            null => $store ? route('merchant.stores.programs.index', $store) : route('merchant.onboarding.wizard.store-basics'),
            default => route('merchant.onboarding.wizard.card-design'),
        };
    }
}
