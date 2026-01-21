<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
        
        $joinUrl = route('join.index', ['slug' => $store->slug, 't' => $store->join_token]);
        return view('stores.qr', compact('store', 'joinUrl'));
    }
}
