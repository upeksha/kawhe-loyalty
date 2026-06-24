<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Support\StoreAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MerchantOnboardingWizardController extends Controller
{
    public const STEP_STORE_BASICS = 'store_basics';
    public const STEP_CARD_DESIGN = 'card_design';
    public const STEP_CUSTOMER_FORM = 'customer_form';
    public const STEP_CARD_READY = 'card_ready';
    public const STEP_CONTINUE_TRIAL = 'continue_trial';

    /**
     * Resolve the onboarding store (first store that is still in onboarding).
     */
    protected function getOnboardingStore(): ?Store
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }
        return $user->stores()
            ->whereNull('onboarding_completed_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * Redirect to the correct wizard step (for index/start).
     */
    public function index()
    {
        $user = Auth::user();
        if ($user->stores()->count() === 0) {
            return redirect()->route('merchant.onboarding.wizard.store-basics');
        }

        $store = $this->getOnboardingStore();
        if (! $store) {
            return redirect()->route('merchant.dashboard');
        }

        $step = $store->onboarding_step;
        if (! $step) {
            return redirect()->route('merchant.dashboard');
        }

        $routes = [
            Store::ONBOARDING_STEP_CARD_DESIGN => 'merchant.onboarding.wizard.card-design',
            Store::ONBOARDING_STEP_CUSTOMER_FORM => 'merchant.onboarding.wizard.customer-form',
            Store::ONBOARDING_STEP_CARD_READY => 'merchant.onboarding.wizard.card-ready',
            Store::ONBOARDING_STEP_CONTINUE_TRIAL => 'merchant.onboarding.wizard.continue-trial',
        ];

        if (isset($routes[$step])) {
            return redirect()->route($routes[$step]);
        }

        return redirect()->route('merchant.dashboard');
    }

    // --- Step 1: Store Basics ---

    public function storeBasics()
    {
        $store = $this->getOnboardingStore();
        // Allow when no store yet (first time) or when going back from a later step
        if ($store) {
            return view('merchant.onboarding.wizard.store-basics', compact('store'));
        }
        if (Auth::user()->stores()->count() > 0) {
            return $this->index();
        }
        return view('merchant.onboarding.wizard.store-basics', ['store' => null]);
    }

    public function storeStoreBasics(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'reward_target' => ['required', 'integer', 'min:1'],
            'reward_title' => ['required', 'string', 'max:255'],
        ]);

        $existing = $this->getOnboardingStore();
        if ($existing) {
            $existing->update(array_merge($validated, [
                'onboarding_step' => Store::ONBOARDING_STEP_CARD_DESIGN,
            ]));
            return redirect()->route('merchant.onboarding.wizard.card-design')
                ->with('success', 'Store updated. Continue with branding.');
        }

        if (Auth::user()->stores()->count() > 0) {
            return redirect()->route('merchant.onboarding.wizard.index');
        }

        $store = Auth::user()->stores()->create(array_merge($validated, [
            'onboarding_step' => Store::ONBOARDING_STEP_CARD_DESIGN,
        ]));
        $store->ensureDefaultProgramExists();

        return redirect()->route('merchant.onboarding.wizard.card-design')
            ->with('success', 'Store created. Now add your branding.');
    }

    // --- Step 2: Card Design ---

    public function cardDesign()
    {
        $store = $this->getOnboardingStore();
        if (! $store) {
            return $this->index();
        }
        // Allow viewing when user has reached this step or any later step (so Back button works)
        $allowedSteps = [
            Store::ONBOARDING_STEP_CARD_DESIGN,
            Store::ONBOARDING_STEP_CUSTOMER_FORM,
            Store::ONBOARDING_STEP_CARD_READY,
            Store::ONBOARDING_STEP_CONTINUE_TRIAL,
        ];
        if (! in_array($store->onboarding_step, $allowedSteps)) {
            return $this->index();
        }
        return view('merchant.onboarding.wizard.card-design', compact('store'));
    }

    public function storeCardDesign(Request $request)
    {
        $store = $this->getOnboardingStore();
        if (! $store) {
            return redirect()->route('merchant.onboarding.wizard.index');
        }

        $validated = $request->validate([
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'pass_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'pass_hero_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $updates = array_filter([
            'brand_color' => $validated['brand_color'] ?? null,
            'background_color' => $validated['background_color'] ?? null,
        ], fn ($v) => $v !== null);

        if ($request->hasFile('logo')) {
            StoreAssets::delete($store->logo_path);
            $updates['logo_path'] = StoreAssets::storeUploaded($request->file('logo'), 'logos');
        }
        if ($request->hasFile('pass_logo')) {
            StoreAssets::delete($store->pass_logo_path);
            $updates['pass_logo_path'] = StoreAssets::storeUploaded($request->file('pass_logo'), 'pass-logos');
        }
        if ($request->hasFile('pass_hero_image')) {
            StoreAssets::delete($store->pass_hero_image_path);
            $updates['pass_hero_image_path'] = StoreAssets::storeUploaded($request->file('pass_hero_image'), 'pass-heroes');
        }

        $updates['onboarding_step'] = Store::ONBOARDING_STEP_CUSTOMER_FORM;
        $store->update($updates);

        return redirect()->route('merchant.onboarding.wizard.customer-form')
            ->with('success', 'Branding saved. Configure which fields to collect from customers.');
    }

    // --- Step 3: Customer Form ---

    public function customerForm()
    {
        $store = $this->getOnboardingStore();
        if (! $store) {
            return $this->index();
        }
        // Allow viewing when user has reached this step or any later step (so Back button works)
        $allowedSteps = [
            Store::ONBOARDING_STEP_CUSTOMER_FORM,
            Store::ONBOARDING_STEP_CARD_READY,
            Store::ONBOARDING_STEP_CONTINUE_TRIAL,
        ];
        if (! in_array($store->onboarding_step, $allowedSteps)) {
            return $this->index();
        }
        $config = $store->registration_form_config ?? [];
        return view('merchant.onboarding.wizard.customer-form', compact('store', 'config'));
    }

    public function storeCustomerForm(Request $request)
    {
        $store = $this->getOnboardingStore();
        if (! $store) {
            return redirect()->route('merchant.onboarding.wizard.index');
        }

        $config = [
            'email' => ['enabled' => true, 'required' => true],
            'first_name' => [
                'enabled' => $request->boolean('first_name_enabled'),
                'required' => $request->boolean('first_name_required'),
            ],
            'last_name' => [
                'enabled' => $request->boolean('last_name_enabled'),
                'required' => $request->boolean('last_name_required'),
            ],
            'phone' => [
                'enabled' => $request->boolean('phone_enabled'),
                'required' => $request->boolean('phone_required'),
            ],
            'birthday' => [
                'enabled' => $request->boolean('birthday_enabled'),
                'required' => $request->boolean('birthday_required'),
            ],
        ];

        foreach (['first_name', 'last_name', 'phone', 'birthday'] as $field) {
            if (! $config[$field]['enabled']) {
                $config[$field]['required'] = false;
            }
        }

        $store->update([
            'registration_form_config' => $config,
            'onboarding_step' => Store::ONBOARDING_STEP_CARD_READY,
        ]);

        return redirect()->route('merchant.onboarding.wizard.card-ready')
            ->with('success', 'Form configured. Share your join link with customers.');
    }

    // --- Step 4: Card Ready ---

    public function cardReady()
    {
        $store = $this->getOnboardingStore();
        if (! $store) {
            return $this->index();
        }
        // Allow viewing when user has reached this step or any later step (so Back button works)
        $allowedSteps = [
            Store::ONBOARDING_STEP_CARD_READY,
            Store::ONBOARDING_STEP_CONTINUE_TRIAL,
        ];
        if (! in_array($store->onboarding_step, $allowedSteps)) {
            return $this->index();
        }
        $joinUrl = $store->join_url;
        return view('merchant.onboarding.wizard.card-ready', compact('store', 'joinUrl'));
    }

    public function advanceToContinueTrial()
    {
        $store = $this->getOnboardingStore();
        if (! $store) {
            return redirect()->route('merchant.onboarding.wizard.index');
        }
        $store->update(['onboarding_step' => Store::ONBOARDING_STEP_CONTINUE_TRIAL]);
        return redirect()->route('merchant.onboarding.wizard.continue-trial');
    }

    // --- Step 5: Continue Trial ---

    public function continueTrial()
    {
        $store = $this->getOnboardingStore();
        if (! $store) {
            return $this->index();
        }
        // Allow viewing when user has reached this step (so Back button works)
        if ($store->onboarding_step !== Store::ONBOARDING_STEP_CONTINUE_TRIAL) {
            return $this->index();
        }
        return view('merchant.onboarding.wizard.continue-trial', compact('store'));
    }

    public function completeOnboarding()
    {
        $store = $this->getOnboardingStore();
        if (! $store) {
            return redirect()->route('merchant.onboarding.wizard.index');
        }
        $store->update([
            'onboarding_step' => null,
            'onboarding_completed_at' => now(),
        ]);
        return redirect()->route('merchant.stores.qr', $store)
            ->with('success', 'You\'re all set! You have 50 free cards to start. Share your QR code with customers.');
    }
}
