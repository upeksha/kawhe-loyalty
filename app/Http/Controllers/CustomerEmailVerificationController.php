<?php

namespace App\Http\Controllers;

use App\Mail\VerifyCustomerEmail;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CustomerEmailVerificationController extends Controller
{
    public function send(Request $request, string $public_token)
    {
        $account = LoyaltyAccount::where('public_token', $public_token)
            ->with('customer')
            ->firstOrFail();

        $customer = $account->customer;

        // Check if customer has email
        if (!$customer->email) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['message' => 'No email on this card.'], 422);
            }
            return back()->withErrors(['email' => 'No email on this card.']);
        }

        // Check if already verified
        if ($customer->email_verified_at) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['message' => 'Email already verified.']);
            }
            return back()->with('message', 'Email already verified.');
        }

        // Enforce resend cooldown (60 seconds)
        if ($customer->email_verification_sent_at && $customer->email_verification_sent_at->diffInSeconds(now()) < 60) {
            $secondsRemaining = 60 - $customer->email_verification_sent_at->diffInSeconds(now());
            $errorMessage = "Please wait {$secondsRemaining} more second(s) before requesting another verification email.";
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['message' => $errorMessage, 'errors' => ['email' => [$errorMessage]]], 422);
            }
            return back()->withErrors(['email' => $errorMessage]);
        }

        // Generate raw token
        $rawToken = Str::random(40);

        // Save verification data
        $customer->update([
            'email_verification_token_hash' => hash('sha256', $rawToken),
            'email_verification_expires_at' => now()->addMinutes(60),
            'email_verification_sent_at' => now(),
        ]);

        // Send email (synchronously in development, queued in production)
        $mailable = new VerifyCustomerEmail($rawToken, $public_token);
        
        try {
            if (config('app.env') === 'local' || config('queue.default') === 'sync') {
                // Send immediately in development
                Mail::to($customer->email)->send($mailable);
                \Log::info('Verification email sent synchronously', ['email' => $customer->email]);
            } else {
                // Queue in production
                Mail::to($customer->email)->queue($mailable);
                \Log::info('Verification email queued', ['email' => $customer->email]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email', [
                'email' => $customer->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check for specific SendGrid errors
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'Maximum credits exceeded') || str_contains($errorMessage, 'Authentication failed')) {
                $errorMessage = 'Email service temporarily unavailable. Please check your SendGrid account or try again later.';
            } elseif (str_contains($errorMessage, 'Failed to authenticate')) {
                $errorMessage = 'Email service configuration error. Please contact support.';
            } else {
                $errorMessage = 'Failed to send verification email. Please try again later.';
            }
            
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['message' => $errorMessage], 500);
            }
            return back()->withErrors(['email' => $errorMessage]);
        }

        $successMessage = 'Verification email sent! Please check your inbox.';
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['message' => $successMessage]);
        }
        return back()->with('message', $successMessage);
    }

    public function verify(Request $request, string $token)
    {
        $tokenHash = hash('sha256', $token);

        $customer = Customer::where('email_verification_token_hash', $tokenHash)
            ->where('email_verification_expires_at', '>=', now())
            ->first();

        if (!$customer) {
            return redirect('/')->withErrors(['email' => 'Invalid or expired verification token.']);
        }

        // Verify the email
        $customer->update([
            'email_verified_at' => now(),
            'email_verification_token_hash' => null,
            'email_verification_expires_at' => null,
        ]);

        // Get public_token from query or find first loyalty account
        $publicToken = $request->query('card');
        
        if (!$publicToken) {
            $loyaltyAccount = $customer->loyaltyAccounts()->first();
            $publicToken = $loyaltyAccount ? $loyaltyAccount->public_token : null;
        }

        if ($publicToken) {
            return redirect()->route('card.show', ['public_token' => $publicToken])
                ->with('message', 'Email verified successfully!');
        }

        return redirect('/')->with('message', 'Email verified successfully!');
    }
}
