<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Services\Wallet\Artwork\CardStyleArtwork;
use App\Support\CardDesign;
use App\Support\CoffeeCardSample;
use Illuminate\Http\Request;

class CardDesignPreviewController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(CoffeeCardSample::available(), 404);
        $model = null;
        if ($request->filled('program_id')) {
            $model = LoyaltyProgram::whereHas('store', fn ($q) => $q->where('user_id', $request->user()->id))->findOrFail($request->input('program_id'));
        } elseif ($request->filled('store_id')) {
            $model = Store::where('user_id', $request->user()->id)->findOrFail($request->input('store_id'));
        }
        $data = $request->validate([
            'card_design' => ['required', 'in:simple,illustrated,photo'],
            'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'reward_target' => ['required', 'integer', 'min:1', 'max:1000000'],
            'platform' => ['required', 'in:apple,google,web'],
            'ready' => ['required', 'boolean'],
            ...CardDesign::rules($request, $model),
        ]);
        $preview = new LoyaltyProgram;
        if ($model) {
            $preview->forceFill($model->only([...CardDesign::FIELDS, 'background_color']));
        }
        $preview->forceFill(collect($data)->except(['card_photo', 'stamp_icon_upload', 'platform', 'ready'])->all());
        $options = CardDesign::options($preview);
        foreach (['card_photo' => 'photo', 'stamp_icon_upload' => 'custom_icon'] as $field => $key) {
            if ($request->hasFile($field)) {
                $options[$key] = file_get_contents($request->file($field)->getRealPath());
            }
        }
        $ready = $request->boolean('ready');
        $target = (int) $data['reward_target'];

        return response(app(CardStyleArtwork::class)->render($target, $ready ? 0 : min(4, $target - 1), $ready, $data['platform'], $options))
            ->header('Content-Type', 'image/png')->header('Cache-Control', 'no-store');
    }
}
