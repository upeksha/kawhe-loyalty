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
                'brand_color',
                'background_color',
                'logo_path',
                'pass_logo_path',
            ])
            ->orderBy('name')
            ->get();

        $stores->transform(function ($store) {
            $store->logo_url = $store->logo_url ?: $store->pass_logo_url;

            return $store;
        });

        return response()->json([
            'stores' => $stores,
        ]);
    }
}
