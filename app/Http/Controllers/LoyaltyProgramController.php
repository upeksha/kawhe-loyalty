<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Services\Billing\UsageService;
use App\Support\StoreAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoyaltyProgramController extends Controller
{
    public function index(Store $store)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();
        $programs = $store->loyaltyPrograms()->withCount('loyaltyAccounts')->get();

        return view('programs.index', compact('store', 'programs'));
    }

    public function create(Store $store)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();
        $usageStats = app(UsageService::class)->getUsageStats(Auth::user());

        return view('programs.create', compact('store', 'usageStats'));
    }

    public function store(Request $request, Store $store)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();
        $usageService = app(UsageService::class);

        if (! $usageService->canCreateProgram(Auth::user())) {
            throw ValidationException::withMessages([
                'name' => 'Your current plan already uses all loyalty card slots. Free includes 1 card, and Pro includes up to 3 cards.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'reward_target' => ['required', 'integer', 'min:1'],
            'reward_title' => ['required', 'string', 'max:255'],
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'require_verification_for_redemption' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'pass_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'pass_hero_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = StoreAssets::storeUploaded($request->file('logo'), 'logos');
        }
        if ($request->hasFile('pass_logo')) {
            $validated['pass_logo_path'] = StoreAssets::storeUploaded($request->file('pass_logo'), 'pass-logos');
        }
        if ($request->hasFile('pass_hero_image')) {
            $validated['pass_hero_image_path'] = StoreAssets::storeUploaded($request->file('pass_hero_image'), 'pass-heroes');
        }

        unset($validated['logo'], $validated['pass_logo'], $validated['pass_hero_image']);

        $registrationFields = ['first_name', 'last_name', 'phone', 'birthday'];
        $formConfig = ['email' => ['enabled' => true, 'required' => true]];
        foreach ($registrationFields as $field) {
            $formConfig[$field] = [
                'enabled' => $request->boolean("{$field}_enabled"),
                'required' => $request->boolean("{$field}_required"),
            ];
        }
        $validated['registration_form_config'] = $formConfig;
        $validated['require_verification_for_redemption'] = $request->boolean('require_verification_for_redemption');
        $validated['sort_order'] = ((int) $store->loyaltyPrograms()->max('sort_order')) + 1;

        $program = $store->loyaltyPrograms()->create($validated);

        return redirect()->route('merchant.stores.programs.edit', [$store, $program])
            ->with('success', 'Loyalty card created successfully.');
    }

    public function edit(Store $store, LoyaltyProgram $program)
    {
        [$store, $program] = $this->resolveProgram($store, $program);
        $hasIssuedCards = LoyaltyAccount::where('loyalty_program_id', $program->id)->exists();

        return view('programs.edit', compact('store', 'program', 'hasIssuedCards'));
    }

    public function update(Request $request, Store $store, LoyaltyProgram $program)
    {
        [$store, $program] = $this->resolveProgram($store, $program);
        $hasIssuedCards = LoyaltyAccount::where('loyalty_program_id', $program->id)->exists();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'reward_target' => $hasIssuedCards ? ['nullable', 'integer', 'min:1'] : ['required', 'integer', 'min:1'],
            'reward_title' => ['required', 'string', 'max:255'],
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'require_verification_for_redemption' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'pass_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'pass_hero_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        if ($hasIssuedCards) {
            if (array_key_exists('reward_target', $validated) && $validated['reward_target'] !== null && (int) $validated['reward_target'] !== (int) $program->reward_target) {
                throw ValidationException::withMessages([
                    'reward_target' => 'Stamps needed for reward is locked after customers have joined this loyalty card. Create a new card if you need a different threshold.',
                ]);
            }

            $validated['reward_target'] = $program->reward_target;
        }

        if ($request->hasFile('logo')) {
            StoreAssets::delete($program->logo_path);
            $validated['logo_path'] = StoreAssets::storeUploaded($request->file('logo'), 'logos');
        }
        if ($request->hasFile('pass_logo')) {
            StoreAssets::delete($program->pass_logo_path);
            $validated['pass_logo_path'] = StoreAssets::storeUploaded($request->file('pass_logo'), 'pass-logos');
        }
        if ($request->hasFile('pass_hero_image')) {
            StoreAssets::delete($program->pass_hero_image_path);
            $validated['pass_hero_image_path'] = StoreAssets::storeUploaded($request->file('pass_hero_image'), 'pass-heroes');
        }

        unset($validated['logo'], $validated['pass_logo'], $validated['pass_hero_image']);

        $registrationFields = ['first_name', 'last_name', 'phone', 'birthday'];
        $formConfig = ['email' => ['enabled' => true, 'required' => true]];
        foreach ($registrationFields as $field) {
            $formConfig[$field] = [
                'enabled' => $request->boolean("{$field}_enabled"),
                'required' => $request->boolean("{$field}_required"),
            ];
        }
        $validated['registration_form_config'] = $formConfig;
        $validated['require_verification_for_redemption'] = $request->boolean('require_verification_for_redemption');

        $program->update($validated);

        return redirect()->route('merchant.stores.programs.edit', [$store, $program])
            ->with('success', 'Loyalty card updated successfully.');
    }

    public function destroy(Store $store, LoyaltyProgram $program)
    {
        [$store, $program] = $this->resolveProgram($store, $program);
        abort_if($program->is_default, 422, 'The default loyalty card cannot be archived from this screen.');

        $program->delete();

        return redirect()->route('merchant.stores.programs.index', $store)->with('success', 'Loyalty card archived.');
    }

    public function restore(Store $store, int $program)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();
        $program = $store->loyaltyPrograms()->withTrashed()->whereKey($program)->firstOrFail();
        $program->restore();

        return redirect()->route('merchant.stores.programs.index', $store)->with('success', 'Loyalty card restored.');
    }

    public function qr(Store $store, LoyaltyProgram $program)
    {
        [$store, $program] = $this->resolveProgram($store, $program);
        $joinUrl = $program->join_url;

        return view('programs.qr', compact('store', 'program', 'joinUrl'));
    }

    private function resolveProgram(Store $store, LoyaltyProgram $program): array
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();
        $program = $store->loyaltyPrograms()->withTrashed()->whereKey($program->id)->firstOrFail();

        return [$store, $program];
    }
}
