<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MerchantOnboardingWizardController;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'store_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $store = $user->stores()->create([
                'name' => $request->string('store_name')->toString(),
                'address' => $request->filled('address') ? $request->string('address')->toString() : null,
                'reward_target' => 9,
                'reward_title' => 'Free coffee',
                'onboarding_step' => MerchantOnboardingWizardController::STEP_STORE_BASICS,
            ]);

            $store->ensureDefaultProgramExists();
            $store->syncDefaultProgramFromStore();

            return $user;
        });

        $verificationQueued = true;
        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            $verificationQueued = false;
            \Log::warning('New merchant verification could not be queued', ['user_id' => $user->id]);
        }

        Auth::login($user);

        return redirect()->route('verification.notice')->with('status', $verificationQueued ? 'verification-link-sent' : 'verification-link-failed');
    }
}
