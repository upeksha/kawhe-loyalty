<?php

namespace App\Http\Controllers;

use App\Mail\CustomerWelcomeEmail;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Services\Billing\UsageService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JoinController extends Controller
{
    /**
     * Redirect short join URL (/j/{code}) to full join flow.
     */
    public function shortRedirect(string $code)
    {
        $store = Store::withTrashed()->where('join_short_code', strtoupper($code))->firstOrFail();
        return redirect()->route('join.index', [
            'slug' => $store->slug,
            't' => $store->join_token,
        ]);
    }

    public function index(Request $request, string $slug)
    {
        $token = $request->query('t');

        $store = $this->findJoinStore($slug, $token);

        if ($archived = $this->archivedStoreResponse($store, $token)) {
            return $archived;
        }

        return view('join.landing', compact('store', 'token'));
    }

    public function existing(Request $request, string $slug)
    {
        $token = $request->query('t');

        $store = $this->findJoinStore($slug, $token);

        if ($archived = $this->archivedStoreResponse($store, $token)) {
            return $archived;
        }

        return view('join.existing', compact('store', 'token'));
    }

    public function lookup(Request $request, string $slug)
    {
        $token = $request->query('t');

        $store = $this->findJoinStore($slug, $token);

        if ($archived = $this->archivedStoreResponse($store, $token)) {
            return $archived;
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $customer = Customer::where('email', $validated['email'])->first();

        if ($customer) {
            $loyaltyAccount = LoyaltyAccount::where('store_id', $store->id)
                ->where('customer_id', $customer->id)
                ->first();

            if ($loyaltyAccount) {
                return redirect()->route('card.show', ['public_token' => $loyaltyAccount->public_token])
                    ->with('show_wallet_nudge', true);
            }
        }

        return back()->withErrors([
            'email' => 'We could not find a card for that email address at ' . $store->name . '. Try a different email, or create a new card if you have not joined yet.',
        ])->withInput();
    }

    public function show(Request $request, string $slug)
    {
        $token = $request->query('t');

        $store = $this->findJoinStore($slug, $token);

        if ($archived = $this->archivedStoreResponse($store, $token)) {
            return $archived;
        }

        return view('join.show', compact('store', 'token'));
    }

    public function store(Request $request, string $slug)
    {
        $token = $request->query('t');

        $store = $this->findJoinStore($slug, $token);

        if ($archived = $this->archivedStoreResponse($store, $token)) {
            return $archived;
        }

        $config = $store->registration_form_config;

        $rules = [
            'email' => ['required', 'email', 'max:255'],
            // Legacy compatibility: older clients submit a single `name` field.
            'name' => ['nullable', 'string', 'max:255'],
        ];
        if (! empty($config['first_name']['enabled'])) {
            $rules['first_name'] = $config['first_name']['required'] ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'];
        }
        if (! empty($config['last_name']['enabled'])) {
            $rules['last_name'] = $config['last_name']['required'] ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'];
        }
        if (! empty($config['phone']['enabled'])) {
            $rules['phone'] = $config['phone']['required'] ? ['required', 'string', 'max:20'] : ['nullable', 'string', 'max:20'];
        }
        if (! empty($config['birthday']['enabled'])) {
            $rules['birthday'] = $config['birthday']['required'] ? ['required', 'date'] : ['nullable', 'date'];
        }

        $validated = $request->validate($rules);

        if (empty($validated['email']) && empty($validated['phone'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => 'Please provide either an email address or a phone number.',
            ]);
        }

        $customerData = [
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'birthday' => isset($validated['birthday']) ? $validated['birthday'] : null,
        ];
        $nameParts = array_filter([$customerData['first_name'] ?? '', $customerData['last_name'] ?? '']);
        $customerData['name'] = $nameParts ? implode(' ', $nameParts) : ($validated['name'] ?? null);

        // Find existing customer or create new one
        $customer = null;

        if (! empty($customerData['email'])) {
            $customer = Customer::where('email', $customerData['email'])->first();
        }

        if (! $customer && ! empty($customerData['phone'])) {
            $customer = Customer::where('phone', $customerData['phone'])->first();
        }

        if (! $customer) {
            $customer = Customer::create($customerData);
        } else {
            $customer->update(array_filter($customerData, fn ($v) => $v !== null));
        }

        // Check if loyalty account already exists for this store and customer
        $existingAccount = LoyaltyAccount::where('store_id', $store->id)
            ->where('customer_id', $customer->id)
            ->first();

        // If account exists, redirect to it (no limit check needed)
        if ($existingAccount) {
            return redirect()->route('card.show', ['public_token' => $existingAccount->public_token])
                ->with('registered', true)
                ->with('show_wallet_nudge', true);
        }

        // Check if merchant can create a new card (limit enforcement)
        $merchant = $store->user;
        
        // Ensure merchant exists
        if (!$merchant) {
            \Log::error('Store has no owner user', [
                'store_id' => $store->id,
                'store_name' => $store->name,
            ]);
            abort(500, 'The store is not ready to accept new joins right now. Please ask staff for help.');
        }
        
        // Try to check usage limit, but allow card creation if check fails
        try {
            $usageService = app(UsageService::class);

            if (!$usageService->canCreateCard($merchant)) {
                // Log the blocked attempt
                try {
                    $stats = $usageService->getUsageStats($merchant);
                    \Log::warning('Customer join blocked due to free plan limit', [
                        'store_id' => $store->id,
                        'store_name' => $store->name,
                        'merchant_id' => $merchant->id,
                        'merchant_email' => $merchant->email,
                        'total_cards_count' => $stats['cards_count'] ?? 0,
                        'non_grandfathered_count' => $stats['non_grandfathered_count'] ?? 0,
                        'grandfathered_count' => $stats['grandfathered_count'] ?? 0,
                        'limit' => $usageService->freeLimit(),
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Error getting usage stats, but card creation blocked', [
                        'error' => $e->getMessage(),
                    ]);
                }

                // Return friendly error page for customer
                return view('join.limit-reached', compact('store', 'token'));
            }
        } catch (\Exception $e) {
            // If usage check fails, log but allow card creation (fail open)
            \Log::error('Error checking usage limit, allowing card creation', [
                'store_id' => $store->id,
                'merchant_id' => $merchant->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Continue to create the card - fail open rather than blocking customers
        }

        // Create new loyalty account
        $loyaltyAccount = LoyaltyAccount::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'public_token' => Str::random(\App\Models\LoyaltyAccount::PUBLIC_TOKEN_LENGTH),
            'stamp_count' => 0,
            'version' => 1,
        ]);

        // Send welcome email with verification if customer has email
        if ($customer->email) {
            // Generate verification token (store-specific)
            $verificationToken = Str::random(40);
            
            // Save verification data on the loyalty account (store-specific verification)
            $loyaltyAccount->update([
                'email_verification_token_hash' => hash('sha256', $verificationToken),
                'email_verification_expires_at' => now()->addMinutes(60),
                'email_verification_sent_at' => now(),
            ]);

            // Send welcome email with verification link (sync = immediate, else high-priority queue)
            $mailable = new CustomerWelcomeEmail($customer, $loyaltyAccount, $verificationToken);
            try {
                if (config('mail.welcome_sync', false)) {
                    Mail::to($customer->email)->send($mailable);
                    \Log::info('Customer welcome email sent synchronously', [
                        'customer_id' => $customer->id,
                        'loyalty_account_id' => $loyaltyAccount->id,
                        'store_id' => $store->id,
                        'email' => $customer->email,
                    ]);
                } else {
                    Mail::to($customer->email)->queue($mailable);
                    \Log::info('Customer welcome email queued successfully', [
                        'customer_id' => $customer->id,
                        'loyalty_account_id' => $loyaltyAccount->id,
                        'store_id' => $store->id,
                        'email' => $customer->email,
                    ]);
                }
            } catch (\Exception $e) {
                // Log the error but don't fail the registration
                \Log::error('Failed to queue customer welcome email', [
                    'customer_id' => $customer->id,
                    'loyalty_account_id' => $loyaltyAccount->id,
                    'store_id' => $store->id,
                    'email' => $customer->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('card.show', ['public_token' => $loyaltyAccount->public_token])
            ->with('registered', true)
            ->with('show_wallet_nudge', true);
    }

    private function findJoinStore(string $slug, ?string $token): Store
    {
        return Store::withTrashed()
            ->where('slug', $slug)
            ->where('join_token', $token)
            ->firstOrFail();
    }

    private function archivedStoreResponse(Store $store, ?string $token): ?View
    {
        if (! $store->trashed()) {
            return null;
        }

        return view('join.archived', compact('store', 'token'));
    }
}
