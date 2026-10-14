<?php

namespace App\Http\Controllers;

use App\Models\StampEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantActivityController extends Controller
{
    public function index(Request $request): View
    {
        $events = StampEvent::query()
            ->with(['loyaltyAccount.customer', 'store', 'user'])
            ->whereIn('store_id', $request->user()->stores()->select('id'))
            ->whereIn('type', ['stamp', 'redeem'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(25);

        return view('merchant.activity.index', compact('events'));
    }
}
