<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $stores = $request->user()
            ->stores()
            ->select([
                'id',
                'name',
                'reward_target',
                'reward_title',
                'require_verification_for_redemption',
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'stores' => $stores,
        ]);
    }
}
