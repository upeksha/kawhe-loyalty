<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MerchantWelcomeEmail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse|\Illuminate\Http\Response
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if ($request->user() && ! $request->user()->is($user)) {
            abort(403);
        }

        if (! $request->isMethod('post')) {
            return response()->view('auth.confirm-email', ['action' => $request->fullUrl()])
                ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
            $this->sendWelcomeOnce($user);
        }

        if ($request->user()?->is($user)) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        return response()->view('auth.email-verified')
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    private function sendWelcomeOnce(User $user): void
    {
        if ($user->is_staff_account) {
            return;
        }

        $queuedAt = now();
        $claimed = User::query()->whereKey($user->id)
            ->whereNotNull('email_verified_at')
            ->whereNull('merchant_welcome_queued_at')
            ->update(['merchant_welcome_queued_at' => $queuedAt]);

        if (! $claimed) {
            return;
        }

        try {
            $mailable = new MerchantWelcomeEmail($user);
            if (config('mail.welcome_sync', false)) {
                Mail::to($user->email)->send($mailable);
            } else {
                Mail::to($user->email)->queue($mailable);
            }
        } catch (\Throwable $e) {
            User::query()->whereKey($user->id)
                ->where('merchant_welcome_queued_at', $queuedAt)
                ->update(['merchant_welcome_queued_at' => null]);
            \Log::error('Failed to send/queue merchant welcome email after verification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
