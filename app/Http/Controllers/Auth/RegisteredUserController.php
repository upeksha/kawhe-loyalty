<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MerchantWelcomeEmail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        // Send welcome email to merchant (sync = immediate, else high-priority queue)
        $mailable = new MerchantWelcomeEmail($user);
        try {
            if (config('mail.welcome_sync', false)) {
                Mail::to($user->email)->send($mailable);
                \Log::info('Merchant welcome email sent synchronously', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            } else {
                Mail::to($user->email)->queue($mailable);
                \Log::info('Merchant welcome email queued successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the registration
            \Log::error('Failed to send/queue merchant welcome email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        Auth::login($user);

        // New merchants have no stores yet: send them straight to onboarding
        return redirect()->route('merchant.onboarding.store');
    }
}
