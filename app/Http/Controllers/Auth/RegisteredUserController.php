<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MerchantOnboardingWizardController;
use App\Models\User;
use App\Rules\PlainSignupName;
use App\Services\MerchantSignupProtection;
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
            'name' => ['required', 'string', 'max:255', new PlainSignupName],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'store_name' => ['required', 'string', 'max:255', new PlainSignupName],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        app(MerchantSignupProtection::class)->verify($request);

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $store = $user->stores()->create([
                'name' => $request->string('store_name')->toString(),
                'address' => null,
                'reward_target' => 9,
                'reward_title' => 'Free coffee',
                'card_design' => \App\Support\CoffeeCardSample::available() ? 'simple' : 'classic',
                'onboarding_step' => MerchantOnboardingWizardController::STEP_STORE_BASICS,
            ]);

            $store->ensureDefaultProgramExists();
            $store->syncDefaultProgramFromStore();

            $user->forceFill(['signup_attribution' => $request->session()->get('signup_attribution')])->save();
            app(\App\Services\MerchantSignupAlerts::class)->record($user, 'registered');

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
        $request->session()->forget('signup_attribution');
        // Only a committed new merchant account can emit the conversion.
        $request->session()->flash('merchant_signup_completed', true);

        return redirect()->route('verification.notice')->with('status', $verificationQueued ? 'verification-link-sent' : 'verification-link-failed');
    }
}
