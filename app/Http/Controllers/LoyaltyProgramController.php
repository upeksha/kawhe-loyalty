<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Services\Billing\UsageService;
use App\Support\StoreAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class LoyaltyProgramController extends Controller
{
    public function index(Store $store)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();
        $programs = $store->loyaltyPrograms()->withCount('loyaltyAccounts')->get();
        $usageStats = app(UsageService::class)->getUsageStats(Auth::user());

        return view('programs.index', compact('store', 'programs', 'usageStats'));
    }

    public function create(Store $store)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();
        $usageService = app(UsageService::class);

        if (! $usageService->canCreateProgram(Auth::user(), $store)) {
            return redirect()->route('merchant.stores.programs.index', $store);
        }

        $usageStats = $usageService->getUsageStats(Auth::user());

        return view('programs.create', compact('store', 'usageStats'));
    }

    public function store(Request $request, Store $store)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();
        $usageService = app(UsageService::class);

        if (! $usageService->canCreateProgram(Auth::user(), $store)) {
            $proCards = (int) config('billing.plans.pro.programs_per_store', 5);
            $proStores = (int) config('billing.plans.pro.stores', 3);
            $planLimit = $usageService->programsPerStoreLimitForUser(Auth::user());

            throw ValidationException::withMessages([
                'name' => $usageService->isSubscribed(Auth::user())
                    ? "This store already has the maximum number of loyalty cards for your plan ({$planLimit} per store)."
                    : "Your free plan includes 1 loyalty card per store. Upgrade to Pro for up to {$proCards} cards per store across {$proStores} stores.",
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

        $validated['registration_form_config'] = \App\Support\RegistrationFormConfig::fromRequest($request);
        $validated['require_verification_for_redemption'] = $request->boolean('require_verification_for_redemption');
        $validated['sort_order'] = ((int) $store->loyaltyPrograms()->max('sort_order')) + 1;

        $program = $store->loyaltyPrograms()->create($validated);
        $this->syncLegacyStoreFieldsFromDefaultProgram($store, $program);

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

        $validated['registration_form_config'] = \App\Support\RegistrationFormConfig::fromRequest($request);
        $validated['require_verification_for_redemption'] = $request->boolean('require_verification_for_redemption');

        $program->update($validated);
        $this->syncLegacyStoreFieldsFromDefaultProgram($store, $program->fresh());

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

    public function qrPdf(Store $store, LoyaltyProgram $program)
    {
        [$store, $program] = $this->resolveProgram($store, $program);

        if ($store->trashed() || $program->trashed()) {
            return redirect()->route('merchant.stores.programs.edit', [$store, $program])->withErrors([
                'program' => 'This loyalty card is archived. Restore it before generating or sharing QR assets.',
            ]);
        }

        $joinUrl = $program->join_url;

        try {
            $qrPng = QrCode::format('png')->size(320)->margin(1)->errorCorrection('L')->generate($joinUrl);
            $qrCodeDataUrl = 'data:image/png;base64,'.base64_encode($qrPng);
        } catch (\Throwable $e) {
            $qrSvg = (string) QrCode::format('svg')->size(320)->margin(1)->errorCorrection('L')->generate($joinUrl);
            $qrCodeDataUrl = 'data:image/svg+xml;base64,'.base64_encode($qrSvg);
        }

        $logoDataUrl = null;
        $logoPath = null;
        foreach ([$program->pass_logo_path, $program->logo_path, $store->pass_logo_path, $store->logo_path] as $candidatePath) {
            if (! empty($candidatePath) && StoreAssets::exists($candidatePath)) {
                $logoPath = $candidatePath;
                break;
            }
        }
        if ($logoPath) {
            $logoDataUrl = $this->binaryToDataUri(StoreAssets::get($logoPath), $logoPath);
        }

        $heroImageDataUrl = null;
        foreach ([$program->pass_hero_image_path, $store->pass_hero_image_path] as $candidatePath) {
            if (! empty($candidatePath) && StoreAssets::exists($candidatePath)) {
                $heroImageDataUrl = $this->binaryToDataUri(StoreAssets::get($candidatePath), $candidatePath);
                break;
            }
        }

        $viewData = [
            'store' => $store,
            'program' => $program,
            'joinUrl' => $joinUrl,
            'qrCodeDataUrl' => $qrCodeDataUrl,
            'logoDataUrl' => $logoDataUrl,
            'heroImageDataUrl' => $heroImageDataUrl,
            'appleWalletBadgeDataUrl' => $this->fileToDataUri(public_path('wallet-badges/add-to-apple-wallet.svg')),
            'googleWalletBadgeDataUrl' => $this->fileToDataUri(public_path('wallet-badges/add-to-google-wallet.svg')),
        ];

        if (request()->boolean('preview')) {
            return response()->view('programs.qr-poster', $viewData);
        }

        $pdf = Pdf::loadView('programs.qr-poster', $viewData)->setPaper('a4', 'portrait');

        return $pdf->download(Str::slug($store->name.' '.$program->name).'-join-poster.pdf');
    }

    public function qrImage(Store $store, LoyaltyProgram $program)
    {
        [$store, $program] = $this->resolveProgram($store, $program);

        if ($store->trashed() || $program->trashed()) {
            return redirect()->route('merchant.stores.programs.edit', [$store, $program])->withErrors([
                'program' => 'This loyalty card is archived. Restore it before generating or sharing QR assets.',
            ]);
        }

        $qrSvg = (string) QrCode::format('svg')->size(1200)->margin(1)->errorCorrection('L')->generate($program->join_url);

        return response($qrSvg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename='.Str::slug($store->name.' '.$program->name).'-qr-code.svg',
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    private function resolveProgram(Store $store, LoyaltyProgram $program): array
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();
        $program = $store->loyaltyPrograms()->withTrashed()->whereKey($program->id)->firstOrFail();

        return [$store, $program];
    }

    private function syncLegacyStoreFieldsFromDefaultProgram(Store $store, LoyaltyProgram $program): void
    {
        if (! $program->is_default && (int) $store->default_loyalty_program_id !== (int) $program->id) {
            return;
        }

        $store->forceFill([
            'reward_target' => $program->reward_target,
            'reward_title' => $program->reward_title,
            'registration_form_config' => $program->registration_form_config,
            'require_verification_for_redemption' => $program->require_verification_for_redemption,
        ])->save();
    }

    private function fileToDataUri(?string $path): ?string
    {
        if (! $path || ! file_exists($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };

        if (! $mime) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function binaryToDataUri(?string $contents, string $pathHint): ?string
    {
        if ($contents === null) {
            return null;
        }

        $extension = strtolower(pathinfo($pathHint, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };

        return $mime ? 'data:'.$mime.';base64,'.base64_encode($contents) : null;
    }
}
