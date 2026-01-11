<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    public function createStore()
    {
        return view('merchant.onboarding.store');
    }

    public function storeStore(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'reward_target' => ['required', 'integer', 'min:1'],
            'reward_title' => ['required', 'string', 'max:255'],
        ]);

        $store = Auth::user()->stores()->create($validated);

        return redirect()->route('merchant.stores.qr', $store)
            ->with('success', 'Welcome! Your first store has been created. Here\'s your QR code to share with customers.');
    }
}
