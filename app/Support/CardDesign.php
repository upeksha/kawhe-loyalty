<?php

namespace App\Support;

use App\Rules\ValidWalletImage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CardDesign
{
    // Lightest possible photo background beneath the browser card’s 66% black overlay.
    public const PHOTO_TEXT_BACKGROUND = '#575757';

    public const FIELDS = ['card_pattern', 'stamp_icon', 'stamp_icon_path', 'card_photo_path', 'stamp_shape', 'reward_icon', 'stamp_color', 'reward_icon_path'];

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
        $icon = $request->input('stamp_icon', $model?->stamp_icon ?? 'coffee');
        $rewardIcon = $request->input('reward_icon', $model?->reward_icon ?? 'match');

        $background = $style === 'illustrated_coffee'
            ? '#263e30'
            : ($request->input('background_color') ?: $model?->background_color ?: '#1F2937');

        return [
            'card_text_color' => ['bail', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/', function ($attribute, $value, $fail) use ($background, $style) {
                if (! self::hasReadableText($value, $background, $style === 'photo')) {
                    $fail($style === 'photo'
                        ? 'Choose a text colour with enough contrast on both the card colour and photo overlay, or use automatic.'
                        : 'Choose a text colour with at least 4.5:1 contrast against the card colour, or use automatic.');
                }
            }],
            'stamp_color' => ['bail', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/', function ($attribute, $value, $fail) use ($background, $style) {
                if (! self::hasReadableText($value, $background, $style === 'photo', 3.0)) {
                    $fail('Choose a stamp colour with enough contrast against the card colour and photo overlay, or use automatic.');
                }
            }],
            'card_pattern' => ['sometimes', 'required', Rule::in(['mountains', 'waves', 'botanical'])],
            'stamp_shape' => ['sometimes', 'required', Rule::in(['circle', 'square', 'rounded'])],
            'reward_icon' => ['sometimes', 'required', Rule::in(['match', 'gift', 'star', 'custom'])],
            'stamp_icon' => ['sometimes', 'required', Rule::in(['coffee', 'bean', 'star', 'heart', 'custom'])],
            'card_photo' => ['bail', Rule::requiredIf($style === 'photo' && blank($model?->card_photo_path)), 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000', new ValidWalletImage('hero')],
            'reward_icon_upload' => ['bail', Rule::requiredIf($rewardIcon === 'custom' && blank($model?->reward_icon_path)), 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000', new ValidWalletImage('logo')],
            'stamp_icon_upload' => ['bail', Rule::requiredIf($icon === 'custom' && blank($model?->stamp_icon_path)), 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000', new ValidWalletImage('logo')],
        ];
    }

    public static function saveUploads(Request $request, array $validated): array
    {
        foreach (['card_photo' => 'card_photo_path', 'stamp_icon_upload' => 'stamp_icon_path', 'reward_icon_upload' => 'reward_icon_path'] as $input => $field) {
            if ($request->hasFile($input)) {
                $validated[$field] = StoreAssets::storeUploaded($request->file($input), 'card-designs');
            }
            unset($validated[$input]);
        }

        return $validated;
    }

    public static function customTextColor($model): ?string
    {
        $color = $model?->card_text_color;
        $background = self::colors($model)[0];

        return is_string($color) && self::hasReadableText($color, $background, $model?->card_design === 'photo')
            ? strtoupper($color)
            : null;
    }

    public static function customStampColor($model): ?string
    {
        $color = $model?->stamp_color;

        return is_string($color) && self::hasReadableText($color, self::colors($model)[0], $model?->card_design === 'photo', 3.0)
            ? strtoupper($color)
            : null;
    }

    private static function hasReadableText(string $color, mixed $background, bool $photo, float $minimumContrast = 4.5): bool
    {
        if (! is_string($background) || preg_match('/^#[0-9A-Fa-f]{6}$/', $background) !== 1
            || preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
            return false;
        }

        return ProgramBranding::contrastRatio($background, $color) >= $minimumContrast
            && (! $photo || (ProgramBranding::contrastRatio(self::PHOTO_TEXT_BACKGROUND, $color) >= $minimumContrast
                && ProgramBranding::contrastRatio('#000000', $color) >= $minimumContrast));
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
        $stampColor = self::customStampColor($model) ?? self::customTextColor($model);
        $icon = $model?->stamp_icon ?: 'coffee';
        $style = self::style($model);

        return [
            ...($stampColor ? ['stamp_color' => $stampColor] : []),
            'style' => $style, 'background' => $bg, 'ink' => $ink,
            ...($model?->stamp_shape && $model->stamp_shape !== 'circle' ? ['shape' => $model->stamp_shape] : []),
            ...($model?->reward_icon && $model->reward_icon !== 'match' ? ['reward_icon' => $model->reward_icon] : []),
            ...($contents && $model?->reward_icon === 'custom' && $model?->reward_icon_path ? ['custom_reward_icon' => StoreAssets::get($model->reward_icon_path)] : []),
            'pattern' => $model?->card_pattern ?: 'mountains',
            'icon' => $icon,
            'photo' => $contents && $model?->card_photo_path ? StoreAssets::get($model->card_photo_path) : null,
            'custom_icon' => $contents && ($style !== 'simple' || $icon === 'custom') && $model?->stamp_icon_path ? StoreAssets::get($model->stamp_icon_path) : null,
        ];
    }
}
