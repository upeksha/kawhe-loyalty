<?php

namespace App\Support;

use App\Rules\ValidWalletImage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CardDesign
{
    public const FIELDS = ['card_pattern', 'stamp_icon', 'stamp_icon_path', 'card_photo_path'];

    public static function style($model): string
    {
        return match ($model?->card_design) {
            'illustrated', 'illustrated_coffee' => 'illustrated',
            'photo' => 'photo',
            default => 'simple',
        };
    }

    public static function formStyle($model = null): string
    {
        return CoffeeCardSample::available()
            ? old('card_design', $model?->card_design === 'classic' ? 'classic' : self::style($model))
            : 'classic';
    }

    public static function rules(Request $request, $model = null): array
    {
        $style = $request->input('card_design', $model?->card_design ?? 'simple');
        $icon = $style === 'simple' ? 'coffee' : $request->input('stamp_icon', $model?->stamp_icon ?? 'coffee');

        return [
            'card_pattern' => ['sometimes', 'required', Rule::in(['mountains', 'waves', 'botanical'])],
            'stamp_icon' => ['sometimes', 'required', Rule::in(['coffee', 'bean', 'star', 'heart', 'custom'])],
            'card_photo' => ['bail', Rule::requiredIf($style === 'photo' && blank($model?->card_photo_path)), 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000', new ValidWalletImage('hero')],
            'stamp_icon_upload' => ['bail', Rule::requiredIf($icon === 'custom' && blank($model?->stamp_icon_path)), 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000', new ValidWalletImage('logo')],
        ];
    }

    public static function saveUploads(Request $request, array $validated): array
    {
        foreach (['card_photo' => 'card_photo_path', 'stamp_icon_upload' => 'stamp_icon_path'] as $input => $field) {
            if ($request->hasFile($input)) {
                $validated[$field] = StoreAssets::storeUploaded($request->file($input), 'card-designs');
            }
            unset($validated[$input]);
        }
        if (($validated['card_design'] ?? null) === 'simple') {
            $validated['stamp_icon'] = 'coffee';
        }

        return $validated;
    }

    public static function colors($model): array
    {
        $bg = $model?->background_color ?: '#263e30';
        if ($model?->card_design === 'illustrated_coffee') {
            return ['#263e30', '#f2ecd7'];
        }
        $theme = ProgramBranding::fromColors('#f2ecd7', $bg);

        return [$theme->bg, $theme->textOnBg];
    }

    public static function options($model, bool $contents = true): array
    {
        [$bg, $ink] = self::colors($model);

        return [
            'style' => self::style($model), 'background' => $bg, 'ink' => $ink,
            'pattern' => $model?->card_pattern ?: 'mountains',
            'icon' => self::style($model) === 'simple' ? 'coffee' : ($model?->stamp_icon ?: 'coffee'),
            'photo' => $contents && $model?->card_photo_path ? StoreAssets::get($model->card_photo_path) : null,
            'custom_icon' => $contents && self::style($model) !== 'simple' && $model?->stamp_icon_path ? StoreAssets::get($model->stamp_icon_path) : null,
        ];
    }
}
