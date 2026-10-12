<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            \Log::warning('Merchant verification could not be queued', ['user_id' => $request->user()->id]);

            return back()->withErrors(['verification' => 'We could not queue the verification email. Please try again shortly.']);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
