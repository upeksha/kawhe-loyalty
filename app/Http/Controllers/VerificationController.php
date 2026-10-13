<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use App\Notifications\VerifyLoyaltyAccount;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function send(Request $request, string $public_token)
    {
        $account = LoyaltyAccount::where('public_token', $public_token)
            ->with('customer')
            ->firstOrFail();

        if ($account->verified_at) {
            return back()->with('message', 'Your email is already verified.');
        }

        if (! $account->customer->email) {
            return back()->withErrors(['email' => 'This card does not have an email address yet. Ask the store to update your details first.']);
        }

        // Send notification (pseudo-anonymous verification)
        $account->notify(new VerifyLoyaltyAccount($account));

        return back()->with('verified_sent', true);
    }

    public function verify(Request $request, string $public_token, $id, $hash)
    {
        $account = LoyaltyAccount::where('public_token', $public_token)
            ->where('id', $id)
            ->firstOrFail();

        if (! hash_equals((string) $hash, sha1($account->customer->email))) {
            abort(403);
        }

        if (! $request->isMethod('post')) {
            return response()->view('auth.confirm-email', ['action' => $request->fullUrl()])
                ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
        }

        if (! $account->verified_at) {
            $account->update(['verified_at' => now()]);
        }

        return redirect()->route('card.show', ['public_token' => $account->public_token])
            ->with('verified_success', true);
    }
}
