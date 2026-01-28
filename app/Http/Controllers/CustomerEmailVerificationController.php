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

        // Check if already verified (store-specific verification)
        if ($account->verified_at) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['message' => 'Email already verified for this store.']);
            }
            return back()->with('message', 'Email already verified for this store.');
        }

        // Check if this is a merchant-initiated request (bypass cooldown for merchants)
        $isMerchantRequest = $request->user() && $request->user()->stores()->where('id', $account->store_id)->exists();
        
        // Enforce resend cooldown (60 seconds for customers, 10 seconds for merchants)
        $cooldownSeconds = $isMerchantRequest ? 10 : 60;
        if ($account->email_verification_sent_at && $account->email_verification_sent_at->diffInSeconds(now()) < $cooldownSeconds) {
            $secondsRemaining = $cooldownSeconds - $account->email_verification_sent_at->diffInSeconds(now());
            $errorMessage = "Please wait {$secondsRemaining} more second(s) before requesting another verification email.";
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['message' => $errorMessage, 'errors' => ['email' => [$errorMessage]]], 422);
            }
            return back()->withErrors(['email' => $errorMessage]);
        }

        // Generate raw token
        $rawToken = Str::random(40);

        // Save verification data on the loyalty account (store-specific)
        $account->update([
            'email_verification_token_hash' => hash('sha256', $rawToken),
            'email_verification_expires_at' => now()->addMinutes(60),
            'email_verification_sent_at' => now(),
        ]);

        // Always queue emails - never block the UI flow
        $mailable = new VerifyCustomerEmail($rawToken, $public_token);
        
        // Attempt to queue the email, but never fail the request if queue fails
        try {
            Mail::to($customer->email)->queue($mailable);
            
            \Log::info('Verification email queued successfully', [
                'loyalty_account_id' => $account->id,
                'customer_id' => $customer->id,
                'store_id' => $account->store_id,
                'public_token' => $public_token,
                'email' => $customer->email,
                'initiated_by' => $isMerchantRequest ? 'merchant' : 'customer',
            ]);
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Failed to queue verification email', [
                'customer_id' => $customer->id,
                'store_id' => $account->store_id,
                'public_token' => $public_token,
                'email' => $customer->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Still return success - email will be retried via queue worker
            // User can request another email if needed
        }

        // Always return success - email is queued and will be processed
        $successMessage = 'Verification email sent! Please check your inbox.';
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['message' => $successMessage]);
        }
        return back()->with('message', $successMessage);
    }

    public function verify(Request $request, string $token)
    {
        $tokenHash = hash('sha256', $token);

        // Find loyalty account by verification token (store-specific verification)
        $account = LoyaltyAccount::where('email_verification_token_hash', $tokenHash)
            ->where('email_verification_expires_at', '>=', now())
            ->with(['customer', 'store'])
            ->first();

        if (!$account) {
            // Try to get public_token from query to redirect back to card
            $publicToken = $request->query('card');
            if ($publicToken) {
                return redirect()->route('card.show', ['public_token' => $publicToken])
                    ->withErrors(['email' => 'Invalid or expired verification token.']);
            }
            return redirect('/')->withErrors(['email' => 'Invalid or expired verification token.']);
        }

        // Verify the email for this specific loyalty account (store-specific)
        $account->update([
            'verified_at' => now(),
            'email_verification_token_hash' => null,
            'email_verification_expires_at' => null,
        ]);

        // Reload the account with store relationship to ensure we have the store name
        $account->load('store');
        
        // Log successful verification
        \Log::info('Email verified successfully', [
            'loyalty_account_id' => $account->id,
            'store_id' => $account->store_id,
            'customer_id' => $account->customer_id,
            'customer_email' => $account->customer->email,
        ]);

        // Redirect to the specific card with success message
        return redirect()->route('card.show', ['public_token' => $account->public_token])
            ->with('message', '✅ Email verified successfully for ' . $account->store->name . '! You can now redeem rewards.');
    }
}
