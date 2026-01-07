<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function show(string $public_token)
    {
        $account = LoyaltyAccount::with(['store', 'customer'])
            ->where('public_token', $public_token)
            ->firstOrFail();

        return view('card.show', compact('account'));
    }
}
