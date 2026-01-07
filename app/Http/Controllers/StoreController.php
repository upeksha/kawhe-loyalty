<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $stores = Auth::user()->stores()->latest()->get();
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
        ]);

        Auth::user()->stores()->create($validated);

        return redirect()->route('stores.index')->with('success', 'Store created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Store $store)
    {
        $this->authorize('update', $store);
        return view('stores.edit', compact('store'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Store $store)
    {
        $this->authorize('update', $store);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'reward_target' => ['required', 'integer', 'min:1'],
            'reward_title' => ['required', 'string', 'max:255'],
        ]);

        $store->update($validated);

        return redirect()->route('stores.index')->with('success', 'Store updated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Store $store)
    {
        // Not used currently, redirect to edit or QR
        $this->authorize('view', $store);
        return redirect()->route('stores.edit', $store);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Store $store)
    {
        $this->authorize('delete', $store);
        $store->delete();
        return redirect()->route('stores.index')->with('success', 'Store deleted successfully.');
    }

    /**
     * Show the QR code for the store.
     */
    public function qr(Store $store)
    {
        $this->authorize('qr', $store);
        $joinUrl = route('join.show', ['slug' => $store->slug, 't' => $store->join_token]);
        return view('stores.qr', compact('store', 'joinUrl'));
    }
}
