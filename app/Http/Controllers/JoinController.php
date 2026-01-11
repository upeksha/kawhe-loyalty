<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class JoinController extends Controller
{
    public function index(Request $request, string $slug)
    {
        $token = $request->query('t');

        $store = Store::where('slug', $slug)
            ->where('join_token', $token)
            ->firstOrFail();

        return view('join.landing', compact('store', 'token'));
    }

    public function existing(Request $request, string $slug)
    {
        $token = $request->query('t');

        $store = Store::where('slug', $slug)
            ->where('join_token', $token)
            ->firstOrFail();

        return view('join.existing', compact('store', 'token'));
    }

    public function lookup(Request $request, string $slug)
    {
        $token = $request->query('t');

        $store = Store::where('slug', $slug)
            ->where('join_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $customer = Customer::where('email', $validated['email'])->first();

        if ($customer) {
            $loyaltyAccount = LoyaltyAccount::where('store_id', $store->id)
                ->where('customer_id', $customer->id)
                ->first();

            if ($loyaltyAccount) {
                return redirect()->route('card.show', ['public_token' => $loyaltyAccount->public_token]);
            }
        }

        return back()->withErrors([
            'email' => 'No card found for this email address at ' . $store->name . '.',
        ])->withInput();
    }

    public function show(Request $request, string $slug)
    {
        $token = $request->query('t');

        $store = Store::where('slug', $slug)
            ->where('join_token', $token)
            ->firstOrFail();

        return view('join.show', compact('store', 'token'));
    }

    public function store(Request $request, string $slug)
    {
        $token = $request->query('t');

        $store = Store::where('slug', $slug)
            ->where('join_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        if (empty($validated['email']) && empty($validated['phone'])) {
            throw ValidationException::withMessages([
                'email' => 'Please provide either an email address or a phone number.',
            ]);
        }

        // Find existing customer or create new one
        $customer = null;

        if (!empty($validated['email'])) {
            $customer = Customer::where('email', $validated['email'])->first();
        }

        if (!$customer && !empty($validated['phone'])) {
            $customer = Customer::where('phone', $validated['phone'])->first();
        }

        if (!$customer) {
            $customer = Customer::create($validated);
        } else {
            // Update name if provided (even if previously set, update it to the latest)
            if (!empty($validated['name'])) {
                $customer->update(['name' => $validated['name']]);
            }
        }

        // Find or create loyalty account specifically for this store and customer
        $loyaltyAccount = LoyaltyAccount::firstOrCreate(
            [
                'store_id' => $store->id,
                'customer_id' => $customer->id,
            ],
            [
                'public_token' => \Illuminate\Support\Str::random(40),
                'stamp_count' => 0,
                'version' => 1,
            ]
        );

        return redirect()->route('card.show', ['public_token' => $loyaltyAccount->public_token])
            ->with('registered', true);
    }
}
