<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StoreController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $stores = Store::queryForUser(Auth::user())->latest()->get();
        return view('stores.index', compact('stores'));
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
            $logoPath = $request->file('logo')->store('logos', 'public');
            $validated['logo_path'] = $logoPath;
        }

        // Handle pass logo upload
        if ($request->hasFile('pass_logo')) {
            $passLogoPath = $request->file('pass_logo')->store('pass-logos', 'public');
            $validated['pass_logo_path'] = $passLogoPath;
        }

        // Handle pass hero image upload
        if ($request->hasFile('pass_hero_image')) {
            $passHeroPath = $request->file('pass_hero_image')->store('pass-heroes', 'public');
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
        $store = Store::queryForUser(Auth::user())->whereKey($store->id)->firstOrFail();
        return view('stores.edit', compact('store'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Store $store)
    {
        $store = Store::queryForUser(Auth::user())->whereKey($store->id)->firstOrFail();

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
            // Delete old logo if exists
            if ($store->logo_path && Storage::disk('public')->exists($store->logo_path)) {
                Storage::disk('public')->delete($store->logo_path);
            }

            // Store new logo
            $logoPath = $request->file('logo')->store('logos', 'public');
            $validated['logo_path'] = $logoPath;
        }

        // Handle pass logo upload
        if ($request->hasFile('pass_logo')) {
            // Delete old pass logo if exists
            if ($store->pass_logo_path && Storage::disk('public')->exists($store->pass_logo_path)) {
                Storage::disk('public')->delete($store->pass_logo_path);
            }

            // Store new pass logo
            $passLogoPath = $request->file('pass_logo')->store('pass-logos', 'public');
            $validated['pass_logo_path'] = $passLogoPath;
        }

        // Handle pass hero image upload
        if ($request->hasFile('pass_hero_image')) {
            // Delete old pass hero image if exists
            if ($store->pass_hero_image_path && Storage::disk('public')->exists($store->pass_hero_image_path)) {
                Storage::disk('public')->delete($store->pass_hero_image_path);
            }

            // Store new pass hero image
            $passHeroPath = $request->file('pass_hero_image')->store('pass-heroes', 'public');
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
        $store->delete();
        return redirect()->route('merchant.stores.index')->with('success', 'Store deleted successfully.');
    }

    /**
     * Show the QR code for the store.
     */
    public function qr(Store $store)
    {
        // First check if store exists
        $store = Store::find($store->id);
        if (!$store) {
            abort(404);
        }
        
        // Then check authorization - return 403 for unauthorized access
        if ($store->user_id !== Auth::id() && !Auth::user()->is_super_admin) {
            abort(403);
        }
        
        $joinUrl = $store->join_url; // short URL /j/{code} when join_short_code is set
        return view('stores.qr', compact('store', 'joinUrl'));
    }

    /**
     * Download A4 PDF poster with QR code for the store (print/email).
     */
    public function qrPdf(Store $store)
    {
        $store = Store::find($store->id);
        if (! $store) {
            abort(404);
        }
        if ($store->user_id !== Auth::id() && ! Auth::user()->is_super_admin) {
            abort(403);
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
        if (! empty($store->logo_path) && Storage::disk('public')->exists($store->logo_path)) {
            $logoPath = public_path('storage/' . $store->logo_path);
            $logoDataUrl = $this->fileToDataUri($logoPath);
        }

        $appleWalletBadgeDataUrl = $this->fileToDataUri(public_path('wallet-badges/add-to-apple-wallet.svg'));
        $googleWalletBadgeDataUrl = $this->fileToDataUri(public_path('wallet-badges/add-to-google-wallet.svg'));

        $rewardWord = $store->reward_title ?: 'stamp';
        $promoHtml = 'Get 1 free <u>' . e($rewardWord) . '</u> instantly when you join!';

        $pdf = Pdf::loadView('stores.qr-poster', [
            'store' => $store,
            'joinUrl' => $joinUrl,
            'qrCodeDataUrl' => $qrCodeDataUrl,
            'logoDataUrl' => $logoDataUrl,
            'appleWalletBadgeDataUrl' => $appleWalletBadgeDataUrl,
            'googleWalletBadgeDataUrl' => $googleWalletBadgeDataUrl,
            'promoHtml' => $promoHtml,
        ])->setPaper('a4', 'portrait');

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
}
