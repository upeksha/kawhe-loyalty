<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            $logoPath = $store->logo_path ?: $store->pass_logo_path;
            $store->logo_url = $logoPath
                ? url(Storage::disk('public')->url($logoPath))
                : null;

            return $store;
        });

        return response()->json([
            'stores' => $stores,
        ]);
    }
}
