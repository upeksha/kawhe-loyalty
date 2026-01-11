<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use App\Notifications\VerifyLoyaltyAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

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

        if (!$account->customer->email) {
            return back()->withErrors(['email' => 'No email address associated with this card.']);
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

        if (!$account->verified_at) {
            $account->update(['verified_at' => now()]);
        }

        return redirect()->route('card.show', ['public_token' => $account->public_token])
            ->with('verified_success', true);
    }
}
