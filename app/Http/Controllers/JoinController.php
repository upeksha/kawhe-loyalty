<?php

namespace App\Http\Controllers;

use App\Mail\CustomerWelcomeEmail;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Support\RegistrationFormConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JoinController extends Controller
{
    /**
     * Redirect short join URL (/j/{code}) to full join flow.
     */
    public function shortRedirect(string $code)
    {
        $program = LoyaltyProgram::withTrashed()->where('join_short_code', strtoupper($code))->first();

        if (! $program) {
            $store = Store::withTrashed()->where('join_short_code', strtoupper($code))->first();

            if (! $store) {
                return $this->invalidJoinResponse();
            }

            $program = $store->resolvedDefaultProgram();

            if (! $program) {
                return $this->invalidJoinResponse($store->slug);
            }
        }

        return redirect()->route('join.index', [
            'slug' => $program->slug,
            't' => $program->join_token,
        ]);
    }

    public function index(Request $request, string $slug)
    {
        $token = $request->query('t');

        $program = $this->resolveJoinProgram($slug, $token);
        if ($program instanceof Response) {
            return $program;
        }

        $store = $program->store;

        if ($archived = $this->archivedStoreResponse($store, $program, $token)) {
            return $archived;
        }

        return view('join.landing', compact('store', 'program', 'token'));
    }

    public function existing(Request $request, string $slug)
    {
        $token = $request->query('t');

        $program = $this->resolveJoinProgram($slug, $token);
        if ($program instanceof Response) {
            return $program;
        }

        $store = $program->store;

        if ($archived = $this->archivedStoreResponse($store, $program, $token)) {
            return $archived;
        }

        return view('join.existing', [
            'store' => $store,
            'program' => $program,
            'token' => $token,
            'phoneLookupEnabled' => RegistrationFormConfig::phoneLookupEnabled($program->registration_form_config ?? []),
        ]);
    }

    public function lookup(Request $request, string $slug)
    {
        $token = $request->query('t');

        $program = $this->resolveJoinProgram($slug, $token);
        if ($program instanceof Response) {
            return $program;
        }

        $store = $program->store;

        if ($archived = $this->archivedStoreResponse($store, $program, $token)) {
            return $archived;
        }

        $formConfig = $program->registration_form_config ?? [];
        $phoneLookupEnabled = RegistrationFormConfig::phoneLookupEnabled($formConfig);

        $rules = [
            'email' => [$phoneLookupEnabled ? 'nullable' : 'required', 'email', 'max:255'],
        ];

        if ($phoneLookupEnabled) {
            $rules['phone'] = ['nullable', 'string', 'max:20'];
        }

        $validated = $request->validate($rules);

        if (empty($validated['email']) && empty($validated['phone'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => $phoneLookupEnabled
                    ? 'Enter the email or phone number you used to join.'
                    : 'Enter the email address you used to join.',
            ]);
        }

        $customer = null;

        if (! empty($validated['email'])) {
            $customer = Customer::where('email', $validated['email'])->first();
        }

        if (! $customer && ! empty($validated['phone'] ?? null)) {
            $customer = Customer::where('phone', $validated['phone'])->first();
        }

        if ($customer) {
            $loyaltyAccount = LoyaltyAccount::where('loyalty_program_id', $program->id)
                ->where('customer_id', $customer->id)
                ->first();

            if ($loyaltyAccount) {
                return redirect()->route('card.show', ['public_token' => $loyaltyAccount->public_token])
                    ->with('show_wallet_nudge', true);
            }
        }

        $errorField = ! empty($validated['email']) ? 'email' : ($phoneLookupEnabled && ! empty($validated['phone'] ?? null) ? 'phone' : 'email');
        $errorMessage = $phoneLookupEnabled
            ? 'We could not find a card for that email or phone number in this loyalty card yet. Try a different detail, or create a new card if you have not joined yet.'
            : 'We could not find a card for that email address in this loyalty card yet. Try a different email, or create a new card if you have not joined yet.';

        return back()->withErrors([
            $errorField => $errorMessage,
        ])->withInput();
    }

    public function show(Request $request, string $slug)
    {
        $token = $request->query('t');

        $program = $this->resolveJoinProgram($slug, $token);
        if ($program instanceof Response) {
            return $program;
        }

        $store = $program->store;

        if ($archived = $this->archivedStoreResponse($store, $program, $token)) {
            return $archived;
        }

        return view('join.show', compact('store', 'program', 'token'));
    }

    public function store(Request $request, string $slug)
    {
        $token = $request->query('t');

        $program = $this->resolveJoinProgram($slug, $token);
        if ($program instanceof Response) {
            return $program;
        }

        $store = $program->store;

        if ($archived = $this->archivedStoreResponse($store, $program, $token)) {
            return $archived;
        }

        $config = $program->registration_form_config;

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
        $existingAccount = LoyaltyAccount::where('loyalty_program_id', $program->id)
            ->where('customer_id', $customer->id)
            ->first();

        // If account exists, redirect to it (no limit check needed)
        if ($existingAccount) {
            return redirect()->route('card.show', ['public_token' => $existingAccount->public_token])
                ->with('registered', true)
                ->with('show_wallet_nudge', true);
        }

        $merchant = $store->user;

        if (! $merchant) {
            \Log::error('Store has no owner user', [
                'store_id' => $store->id,
                'store_name' => $store->name,
            ]);
            abort(500, 'The store is not ready to accept new joins right now. Please ask staff for help.');
        }

        $usageService = app(\App\Services\Billing\UsageService::class);
        if (! $usageService->canAcceptNewCustomer($program)) {
            return view('join.limit-reached', compact('store', 'program', 'token'));
        }

        // Create new loyalty account
        $loyaltyAccount = LoyaltyAccount::create([
            'store_id' => $store->id,
            'loyalty_program_id' => $program->id,
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
                'email_verification_expires_at' => now()->addDay(),
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
                        'loyalty_program_id' => $program->id,
                        'email' => $customer->email,
                    ]);
                } else {
                    Mail::to($customer->email)->queue($mailable);
                    \Log::info('Customer welcome email queued successfully', [
                        'customer_id' => $customer->id,
                        'loyalty_account_id' => $loyaltyAccount->id,
                        'store_id' => $store->id,
                        'loyalty_program_id' => $program->id,
                        'email' => $customer->email,
                    ]);
                }
            } catch (\Exception $e) {
                // Log the error but don't fail the registration
                \Log::error('Failed to queue customer welcome email', [
                    'customer_id' => $customer->id,
                    'loyalty_account_id' => $loyaltyAccount->id,
                    'store_id' => $store->id,
                    'loyalty_program_id' => $program->id,
                    'email' => $customer->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('card.show', ['public_token' => $loyaltyAccount->public_token])
            ->with('registered', true)
            ->with('show_wallet_nudge', true);
    }

    private function resolveJoinProgram(string $slug, ?string $token): LoyaltyProgram|Response
    {
        if (! $token) {
            return $this->invalidJoinResponse($slug);
        }

        $program = LoyaltyProgram::withTrashed()
            ->where('slug', $slug)
            ->where('join_token', $token)
            ->with('store')
            ->first();

        if ($program) {
            return $program;
        }

        $store = Store::withTrashed()
            ->where('slug', $slug)
            ->where('join_token', $token)
            ->first();

        if (! $store) {
            return $this->invalidJoinResponse($slug);
        }

        $program = $store->resolvedDefaultProgram();

        if (! $program) {
            return $this->invalidJoinResponse($slug);
        }

        return $program;
    }

    private function invalidJoinResponse(?string $slug = null): Response
    {
        $program = $slug
            ? LoyaltyProgram::where('slug', $slug)->with('store')->first()
            : null;
        $store = $program?->store ?? ($slug ? Store::where('slug', $slug)->first() : null);

        return response()->view('join.invalid', compact('store', 'program'), 404);
    }

    private function archivedStoreResponse(Store $store, LoyaltyProgram $program, ?string $token): ?View
    {
        if (! $store->trashed() && ! $program->trashed()) {
            return null;
        }

        return view('join.archived', compact('store', 'program', 'token'));
    }
}
