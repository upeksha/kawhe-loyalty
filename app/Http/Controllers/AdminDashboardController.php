<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\User;
use App\Models\StampEvent;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'total_stores' => Store::count(),
            'total_stamps_today' => StampEvent::whereDate('created_at', today())->count(),
        ];

        $recent_stores = Store::with('user')->latest()->take(10)->get();
        $recent_stamps = StampEvent::with(['loyaltyAccount.customer', 'store'])->latest()->take(20)->get();

        return view('admin.dashboard', compact('stats', 'recent_stores', 'recent_stamps'));
    }
}
