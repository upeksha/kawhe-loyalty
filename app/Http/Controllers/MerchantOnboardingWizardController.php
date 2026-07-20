<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\Wallet\Artwork\WalletArtworkService;
use App\Services\Wallet\Artwork\WalletImageValidator;
use App\Services\Wallet\WalletPreviewDataFactory;
use App\Support\RegistrationFormConfig;
use App\Support\StoreAssets;
use App\Support\StoreBrandingRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MerchantOnboardingWizardController extends Controller
{
    public function __construct(
        private readonly WalletArtworkService $walletArtworkService,
        private readonly WalletImageValidator $walletImageValidator,
        private readonly WalletPreviewDataFactory $walletPreviewDataFactory,
    ) {}

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
            self::STEP_STORE_BASICS => 'merchant.onboarding.wizard.store-basics',
            Store::ONBOARDING_STEP_CARD_DESIGN => 'merchant.onboarding.wizard.card-design',
            Store::ONBOARDING_STEP_CUSTOMER_FORM => 'merchant.onboarding.wizard.customer-form',
            Store::ONBOARDING_STEP_CARD_READY => 'merchant.onboarding.wizard.card-ready',
            Store::ONBOARDING_STEP_CONTINUE_TRIAL => 'merchant.onboarding.wizard.card-ready',
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
            $existing->syncDefaultProgramFromStore();
            $program = $existing->resolvedDefaultProgram();
            if ($program?->pass_logo_path && $program?->pass_hero_image_path) {
                $this->walletArtworkService->syncForProgram($program);
            }

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
        $store->syncDefaultProgramFromStore();

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

        $walletPreview = $this->walletPreviewDataFactory->forStore($store);

        return view('merchant.onboarding.wizard.card-design', compact('store', 'walletPreview'));
    }

    public function storeCardDesign(Request $request)
    {
        $store = $this->getOnboardingStore();
        if (! $store) {
            return redirect()->route('merchant.onboarding.wizard.index');
        }

        $validated = $request->validate(StoreBrandingRules::validationRules($store));
        $imageWarnings = $this->walletImageValidator->warningsForRequest($request, [
            'logo' => 'logo',
            'pass_logo' => 'logo',
            'pass_hero_image' => 'hero',
        ]);

        $updates = [
            'brand_color' => $validated['brand_color'],
            'background_color' => $validated['background_color'],
        ];

        if ($request->hasFile('logo')) {
            $updates['logo_path'] = StoreAssets::storeUploaded($request->file('logo'), 'logos');
        }
        if ($request->hasFile('pass_logo')) {
            $updates['pass_logo_path'] = StoreAssets::storeUploaded($request->file('pass_logo'), 'pass-logos');
        }
        if ($request->hasFile('pass_hero_image')) {
            $updates['pass_hero_image_path'] = StoreAssets::storeUploaded($request->file('pass_hero_image'), 'pass-heroes');
        }

        $updates['onboarding_step'] = Store::ONBOARDING_STEP_CUSTOMER_FORM;
        $store->update($updates);
        $store->syncDefaultProgramFromStore();
        $program = $store->fresh()->resolvedDefaultProgram();
        if ($program) {
            $this->walletArtworkService->syncForProgram($program);
        }

        return redirect()->route('merchant.onboarding.wizard.customer-form')
            ->with('success', 'Branding saved. Configure which fields to collect from customers.')
            ->with('wallet_image_warnings', $imageWarnings);
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

        $store->update([
            'registration_form_config' => RegistrationFormConfig::fromRequest($request),
            'onboarding_step' => Store::ONBOARDING_STEP_CARD_READY,
        ]);
        $store->syncDefaultProgramFromStore();

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
        $program = $store->resolvedDefaultProgram();
        $joinUrl = $program?->join_url ?? $store->join_url;

        return view('merchant.onboarding.wizard.card-ready', compact('store', 'program', 'joinUrl'));
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
            ->with('success', 'You\'re all set. Share your QR code or join link with customers to start collecting signups.');
    }
}
