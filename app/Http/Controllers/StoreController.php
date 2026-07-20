<?php

namespace App\Http\Controllers;

use App\Models\AppleWalletRegistration;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\SupportAuditLog;
use App\Rules\ValidWalletImage;
use App\Services\Billing\UsageService;
use App\Services\Support\MerchantRecoveryService;
use App\Services\Wallet\Artwork\WalletArtworkService;
use App\Services\Wallet\Artwork\WalletImageValidator;
use App\Support\StoreAssets;
use App\Support\StoreBrandingRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StoreController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MerchantRecoveryService $merchantRecoveryService,
        private readonly WalletArtworkService $walletArtworkService,
        private readonly WalletImageValidator $walletImageValidator,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $allStores = Store::queryForUser(Auth::user(), includeArchived: true)->latest()->get();
        $stores = $allStores->whereNull('deleted_at')->values();
        $archivedStores = $allStores->whereNotNull('deleted_at')->values();
        $usageStats = app(UsageService::class)->getUsageStats(Auth::user());

        return view('stores.index', compact('stores', 'archivedStores', 'usageStats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (! app(UsageService::class)->canCreateStore(Auth::user())) {
            return redirect()->route('merchant.stores.index');
        }

        return view('stores.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $usageService = app(UsageService::class);

        if (! $usageService->canCreateStore(Auth::user())) {
            return redirect()->route('billing.index')
                ->withErrors([
                    'error' => 'Your current plan has reached its store limit. Free includes 1 store; Pro includes up to 3 stores.',
                ]);
        }

        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'reward_target' => ['required', 'integer', 'min:1'],
            'reward_title' => ['required', 'string', 'max:255'],
        ], StoreBrandingRules::validationRules()));
        $imageWarnings = $this->walletImageValidator->warningsForRequest($request, [
            'logo' => 'logo',
            'pass_logo' => 'logo',
            'pass_hero_image' => 'hero',
        ]);

        $validated['logo_path'] = StoreAssets::storeUploaded($request->file('logo'), 'logos');
        $validated['pass_logo_path'] = StoreAssets::storeUploaded($request->file('pass_logo'), 'pass-logos');
        $validated['pass_hero_image_path'] = StoreAssets::storeUploaded($request->file('pass_hero_image'), 'pass-heroes');

        unset($validated['logo'], $validated['pass_logo'], $validated['pass_hero_image']);

        $store = Auth::user()->stores()->create($validated);

        $program = $store->loyaltyPrograms()->create([
            'name' => $validated['reward_title'],
            'slug' => $store->slug,
            'reward_target' => $validated['reward_target'],
            'reward_title' => $validated['reward_title'],
            'join_token' => $store->join_token,
            'join_short_code' => $store->join_short_code,
            'brand_color' => $validated['brand_color'],
            'background_color' => $validated['background_color'],
            'logo_path' => $validated['logo_path'],
            'pass_logo_path' => $validated['pass_logo_path'],
            'pass_hero_image_path' => $validated['pass_hero_image_path'],
            'require_verification_for_redemption' => true,
            'registration_form_config' => Store::defaultRegistrationFormConfig(),
            'is_default' => true,
            'sort_order' => 1,
        ]);

        $store->forceFill(['default_loyalty_program_id' => $program->id])->save();
        $this->walletArtworkService->syncForProgram($program);

        return redirect()->route('merchant.stores.index')
            ->with('success', 'Store created successfully.')
            ->with('wallet_image_warnings', $imageWarnings);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Store $store)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();
        $walletHealth = $this->walletHealth($store);
        $defaultProgram = $store->resolvedDefaultProgram();
        $defaultProgram?->loadCount('loyaltyAccounts');

        return view('stores.edit', compact('store', 'walletHealth', 'defaultProgram'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Store $store)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', new ValidWalletImage('logo')],
            'pass_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', new ValidWalletImage('logo')],
            'pass_hero_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', new ValidWalletImage('hero')],
        ]);
        $imageWarnings = $this->walletImageValidator->warningsForRequest($request, [
            'logo' => 'logo',
            'pass_logo' => 'logo',
            'pass_hero_image' => 'hero',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoPath = StoreAssets::storeUploaded($request->file('logo'), 'logos');
            $validated['logo_path'] = $logoPath;
        }

        // Handle pass logo upload
        if ($request->hasFile('pass_logo')) {
            $passLogoPath = StoreAssets::storeUploaded($request->file('pass_logo'), 'pass-logos');
            $validated['pass_logo_path'] = $passLogoPath;
        }

        // Handle pass hero image upload
        if ($request->hasFile('pass_hero_image')) {
            $passHeroPath = StoreAssets::storeUploaded($request->file('pass_hero_image'), 'pass-heroes');
            $validated['pass_hero_image_path'] = $passHeroPath;
        }

        // Remove file inputs from validated array
        unset($validated['logo'], $validated['pass_logo'], $validated['pass_hero_image']);

        // Remove paths from validated if not uploaded (to avoid overwriting with null)
        if (! isset($validated['logo_path'])) {
            unset($validated['logo_path']);
        }
        if (! isset($validated['pass_logo_path'])) {
            unset($validated['pass_logo_path']);
        }
        if (! isset($validated['pass_hero_image_path'])) {
            unset($validated['pass_hero_image_path']);
        }

        $store->update($validated);

        foreach ($store->loyaltyPrograms()->get() as $program) {
            $this->walletArtworkService->syncForProgram($program);
        }

        return redirect()->route('merchant.stores.index')
            ->with('success', 'Store updated successfully.')
            ->with('wallet_image_warnings', $imageWarnings);
    }

    /**
     * Display the specified resource.
     */
    public function show(Store $store)
    {
        // Not used currently, redirect to edit or QR
        $this->authorize('view', $store);

        return redirect()->route('merchant.stores.edit', $store);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Store $store)
    {
        $this->authorize('delete', $store);
        if ($store->trashed()) {
            return redirect()->route('merchant.stores.index')->with('success', 'Store is already archived.');
        }

        $store->delete();

        return redirect()->route('merchant.stores.index')->with('success', 'Store archived. New joins and QR sharing are now paused, but customers and history are preserved.');
    }

    public function restore(Store $store)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();
        $this->authorize('restore', $store);

        if (! $store->trashed()) {
            return redirect()->route('merchant.stores.edit', $store)->with('success', 'Store is already active.');
        }

        $store->restore();

        return redirect()->route('merchant.stores.edit', $store)->with('success', 'Store restored. Join links, QR sharing, and wallet activity are active again.');
    }

    /**
     * Show the QR code for the store.
     */
    public function qr(Store $store)
    {
        // First check if store exists
        $store = Store::withTrashed()->find($store->id);
        if (! $store) {
            abort(404);
        }

        // Then check authorization - return 403 for unauthorized access
        if ($store->user_id !== Auth::id() && ! Auth::user()->is_super_admin) {
            abort(403);
        }

        if ($store->trashed()) {
            return redirect()->route('merchant.stores.edit', $store)->withErrors([
                'store' => 'This store is archived. Restore it before sharing the join QR code again.',
            ]);
        }

        $joinUrl = $store->join_url; // short URL /j/{code} when join_short_code is set
        $walletHealth = $this->walletHealth($store);

        return view('stores.qr', compact('store', 'joinUrl', 'walletHealth'));
    }

    private function walletHealth(Store $store): array
    {
        $accountIds = LoyaltyAccount::where('store_id', $store->id)->pluck('id');
        $activeCards = $accountIds->count();
        $activeAppleRegistrations = $accountIds->isEmpty()
            ? 0
            : AppleWalletRegistration::query()->whereIn('loyalty_account_id', $accountIds)->active()->count();

        $lastAppleRegistrationAt = $accountIds->isEmpty()
            ? null
            : AppleWalletRegistration::query()->whereIn('loyalty_account_id', $accountIds)->active()->max('last_registered_at');

        $recentWalletSyncs = SupportAuditLog::query()
            ->where('store_id', $store->id)
            ->where('event_type', 'wallet_sync')
            ->latest()
            ->limit(5)
            ->get(['status', 'message', 'created_at']);

        $walletFailuresLast7Days = SupportAuditLog::query()
            ->where('store_id', $store->id)
            ->where('event_type', 'wallet_sync')
            ->where('status', '!=', 'success')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $walletReady = ! empty($store->reward_title)
            && (int) ($store->reward_target ?? 0) > 0
            && ! empty($store->background_color)
            && (! empty($store->logo_path) || ! empty($store->pass_logo_path));

        if (! $walletReady) {
            $statusLabel = 'Needs setup';
            $statusIcon = 'setup';
            $statusTone = 'bg-amber-100 text-amber-700';
            $recommendedAction = 'Finish wallet branding first. Add a readable background color and at least one logo so saved passes look complete.';
        } elseif ($activeCards === 0) {
            $statusLabel = 'Ready for first customer';
            $statusIcon = 'rocket';
            $statusTone = 'bg-brand-100 text-brand-700';
            $recommendedAction = 'Your wallet setup looks ready. Save one test card to Apple Wallet and Google Wallet before sharing widely.';
        } elseif ($walletFailuresLast7Days > 0) {
            $statusLabel = 'Needs attention';
            $statusIcon = 'attention';
            $statusTone = 'bg-amber-100 text-amber-700';
            $recommendedAction = 'Recent wallet refreshes have had issues. Queue a store-wide wallet refresh, then ask affected customers to reopen or re-add their pass if it still looks stale.';
        } elseif ($activeAppleRegistrations === 0) {
            $statusLabel = 'Launchable';
            $statusIcon = 'launch';
            $statusTone = 'bg-stone-100 text-stone-700';
            $recommendedAction = 'No active Apple Wallet registrations are on file yet. That usually means customers have not added the pass yet, or they removed it. Test with one fresh add before launch.';
        } else {
            $statusLabel = 'Healthy';
            $statusIcon = 'check';
            $statusTone = 'bg-emerald-100 text-emerald-700';
            $recommendedAction = 'Wallet setup looks healthy. If a customer reports a stale card, queue a refresh first, then ask them to reopen the pass. Re-adding is the fallback if Apple or Google still shows cached artwork.';
        }

        return [
            'ready' => $walletReady,
            'status_label' => $statusLabel,
            'status_icon' => $statusIcon,
            'status_tone' => $statusTone,
            'active_cards' => $activeCards,
            'active_apple_registrations' => $activeAppleRegistrations,
            'last_apple_registration_at' => $lastAppleRegistrationAt,
            'wallet_failures_last_7_days' => $walletFailuresLast7Days,
            'recent_wallet_syncs' => $recentWalletSyncs,
            'recommended_action' => $recommendedAction,
        ];
    }

    public function refreshWallets(Store $store)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();

        if ($store->trashed()) {
            return back()->withErrors(['store' => 'Restore this store before queueing wallet refreshes.']);
        }

        $queuedCount = $this->merchantRecoveryService->queueStoreWalletRefresh($store, Auth::id());

        return back()->with('success', "Queued wallet refresh for {$queuedCount} card".($queuedCount === 1 ? '' : 's').'.');
    }

    /**
     * Download A4 PDF poster with QR code for the store (print/email).
     */
    public function qrPdf(Store $store)
    {
        $store = Store::withTrashed()->find($store->id);
        if (! $store) {
            abort(404);
        }
        if ($store->user_id !== Auth::id() && ! Auth::user()->is_super_admin) {
            abort(403);
        }
        if ($store->trashed()) {
            return redirect()->route('merchant.stores.edit', $store)->withErrors([
                'store' => 'This store is archived. Restore it before generating or sharing join posters.',
            ]);
        }

        $joinUrl = $store->join_url;

        $qrCodeDataUrl = null;
        try {
            $qrPng = QrCode::format('png')->size(320)->margin(1)->errorCorrection('L')->generate($joinUrl);
            $qrCodeDataUrl = 'data:image/png;base64,'.base64_encode($qrPng);
        } catch (\Throwable $e) {
            $qrSvg = (string) QrCode::format('svg')->size(320)->margin(1)->errorCorrection('L')->generate($joinUrl);
            $qrCodeDataUrl = 'data:image/svg+xml;base64,'.base64_encode($qrSvg);
        }

        $logoDataUrl = null;
        $logoPath = null;
        foreach ([$store->pass_logo_path, $store->logo_path] as $candidatePath) {
            if (! empty($candidatePath) && StoreAssets::exists($candidatePath)) {
                $logoPath = $candidatePath;
                break;
            }
        }
        if ($logoPath) {
            $logoDataUrl = $this->binaryToDataUri(StoreAssets::get($logoPath), $logoPath);
        }

        $heroImageDataUrl = null;
        if (! empty($store->pass_hero_image_path) && StoreAssets::exists($store->pass_hero_image_path)) {
            $heroImageDataUrl = $this->binaryToDataUri(StoreAssets::get($store->pass_hero_image_path), $store->pass_hero_image_path);
        }

        $appleWalletBadgeDataUrl = $this->fileToDataUri(public_path('wallet-badges/add-to-apple-wallet.svg'));
        $googleWalletBadgeDataUrl = $this->fileToDataUri(public_path('wallet-badges/add-to-google-wallet.svg'));

        $rewardWord = $store->reward_title ?: 'stamp';
        $promoHtml = 'Get 1 free <u>'.e($rewardWord).'</u> instantly when you join!';

        $viewData = [
            'store' => $store,
            'joinUrl' => $joinUrl,
            'qrCodeDataUrl' => $qrCodeDataUrl,
            'logoDataUrl' => $logoDataUrl,
            'heroImageDataUrl' => $heroImageDataUrl,
            'appleWalletBadgeDataUrl' => $appleWalletBadgeDataUrl,
            'googleWalletBadgeDataUrl' => $googleWalletBadgeDataUrl,
            'promoHtml' => $promoHtml,
        ];

        if (request()->boolean('preview')) {
            return response()->view('stores.qr-poster', $viewData);
        }

        $pdf = Pdf::loadView('stores.qr-poster', $viewData)->setPaper('a4', 'portrait');

        $filename = Str::slug($store->name).'-join-poster.pdf';

        return $pdf->download($filename);
    }

    /**
     * Download just the QR code as an SVG image.
     */
    public function qrImage(Store $store)
    {
        $store = Store::withTrashed()->find($store->id);
        if (! $store) {
            abort(404);
        }
        if ($store->user_id !== Auth::id() && ! Auth::user()->is_super_admin) {
            abort(403);
        }
        if ($store->trashed()) {
            return redirect()->route('merchant.stores.edit', $store)->withErrors([
                'store' => 'This store is archived. Restore it before generating or sharing QR assets.',
            ]);
        }

        $joinUrl = $store->join_url;
        $qrSvg = (string) QrCode::format('svg')->size(1200)->margin(1)->errorCorrection('L')->generate($joinUrl);
        $filename = Str::slug($store->name).'-qr-code.svg';

        return response($qrSvg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename='.$filename,
            'Cache-Control' => 'no-cache, private',
        ]);
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
