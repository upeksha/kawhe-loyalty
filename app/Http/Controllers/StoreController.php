<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\Support\MerchantRecoveryService;
use App\Support\StoreAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StoreController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MerchantRecoveryService $merchantRecoveryService
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $allStores = Store::queryForUser(Auth::user(), includeArchived: true)->latest()->get();
        $stores = $allStores->whereNull('deleted_at')->values();
        $archivedStores = $allStores->whereNotNull('deleted_at')->values();

        return view('stores.index', compact('stores', 'archivedStores'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('stores.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'reward_target' => ['required', 'integer', 'min:1'],
            'reward_title' => ['required', 'string', 'max:255'],
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'pass_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'pass_hero_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
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

        Auth::user()->stores()->create($validated);

        return redirect()->route('merchant.stores.index')->with('success', 'Store created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Store $store)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();
        return view('stores.edit', compact('store'));
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
            'reward_target' => ['required', 'integer', 'min:1'],
            'reward_title' => ['required', 'string', 'max:255'],
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'require_verification_for_redemption' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'pass_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'pass_hero_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $validated['require_verification_for_redemption'] = $request->boolean('require_verification_for_redemption');

        // Handle logo upload
        if ($request->hasFile('logo')) {
            StoreAssets::delete($store->logo_path);
            $logoPath = StoreAssets::storeUploaded($request->file('logo'), 'logos');
            $validated['logo_path'] = $logoPath;
        }

        // Handle pass logo upload
        if ($request->hasFile('pass_logo')) {
            StoreAssets::delete($store->pass_logo_path);
            $passLogoPath = StoreAssets::storeUploaded($request->file('pass_logo'), 'pass-logos');
            $validated['pass_logo_path'] = $passLogoPath;
        }

        // Handle pass hero image upload
        if ($request->hasFile('pass_hero_image')) {
            StoreAssets::delete($store->pass_hero_image_path);
            $passHeroPath = StoreAssets::storeUploaded($request->file('pass_hero_image'), 'pass-heroes');
            $validated['pass_hero_image_path'] = $passHeroPath;
        }

        // Remove file inputs from validated array
        unset($validated['logo'], $validated['pass_logo'], $validated['pass_hero_image']);

        // Remove paths from validated if not uploaded (to avoid overwriting with null)
        if (!isset($validated['logo_path'])) {
            unset($validated['logo_path']);
        }
        if (!isset($validated['pass_logo_path'])) {
            unset($validated['pass_logo_path']);
        }
        if (!isset($validated['pass_hero_image_path'])) {
            unset($validated['pass_hero_image_path']);
        }

        // Build registration_form_config from checkbox inputs
        $registrationFields = ['first_name', 'last_name', 'phone', 'birthday'];
        $formConfig = ['email' => ['enabled' => true, 'required' => true]];
        foreach ($registrationFields as $field) {
            $formConfig[$field] = [
                'enabled'  => $request->boolean("{$field}_enabled"),
                'required' => $request->boolean("{$field}_required"),
            ];
        }
        $validated['registration_form_config'] = $formConfig;

        $store->update($validated);

        return redirect()->route('merchant.stores.index')->with('success', 'Store updated successfully.');
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
        if (!$store) {
            abort(404);
        }
        
        // Then check authorization - return 403 for unauthorized access
        if ($store->user_id !== Auth::id() && !Auth::user()->is_super_admin) {
            abort(403);
        }

        if ($store->trashed()) {
            return redirect()->route('merchant.stores.edit', $store)->withErrors([
                'store' => 'This store is archived. Restore it before sharing the join QR code again.',
            ]);
        }
        
        $joinUrl = $store->join_url; // short URL /j/{code} when join_short_code is set
        return view('stores.qr', compact('store', 'joinUrl'));
    }

    public function refreshWallets(Store $store)
    {
        $store = Store::queryForUser(Auth::user(), includeArchived: true)->whereKey($store->id)->firstOrFail();

        if ($store->trashed()) {
            return back()->withErrors(['store' => 'Restore this store before queueing wallet refreshes.']);
        }

        $queuedCount = $this->merchantRecoveryService->queueStoreWalletRefresh($store, Auth::id());

        return back()->with('success', "Queued wallet refresh for {$queuedCount} card" . ($queuedCount === 1 ? '' : 's') . '.');
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
            $qrCodeDataUrl = 'data:image/png;base64,' . base64_encode($qrPng);
        } catch (\Throwable $e) {
            $qrSvg = (string) QrCode::format('svg')->size(320)->margin(1)->errorCorrection('L')->generate($joinUrl);
            $qrCodeDataUrl = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
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
        $promoHtml = 'Get 1 free <u>' . e($rewardWord) . '</u> instantly when you join!';

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

        $filename = Str::slug($store->name) . '-join-poster.pdf';

        return $pdf->download($filename);
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

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
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

        return $mime ? 'data:' . $mime . ';base64,' . base64_encode($contents) : null;
    }
}
