<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Validation\Rule;

class StoreBrandingRules
{
    /**
     * Validation rules for store branding colors and image assets.
     *
     * When $store is null (new store create), all image uploads are required.
     * When $store exists (onboarding revisit), uploads are required only if not already saved.
     *
     * @return array<string, mixed>
     */
    public static function validationRules(?Store $store = null): array
    {
        $imageRules = ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048'];
        $stampIconRules = ['file', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'];
        $selectedStyle = request()->input('wallet_card_style', $store?->wallet_card_style ?? Store::WALLET_CARD_STYLE_CLASSIC);
        $isClassic = $selectedStyle !== Store::WALLET_CARD_STYLE_ABSTRACT;

        return [
            'brand_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'wallet_card_style' => ['nullable', 'string', Rule::in(Store::WALLET_CARD_STYLES)],
            'wallet_background_pattern' => ['nullable', 'string', Rule::in(Store::WALLET_BACKGROUND_PATTERNS)],
            'wallet_pattern_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => self::imageFieldRules($store, 'logo_path', $imageRules),
            'pass_logo' => self::imageFieldRules($store, 'pass_logo_path', $imageRules),
            'pass_hero_image' => self::imageFieldRules($store, 'pass_hero_image_path', $imageRules, $isClassic),
            'wallet_stamp_icon' => self::imageFieldRules($store, 'wallet_stamp_icon_path', $stampIconRules, false),
        ];
    }

    /**
     * @param  array<int, string>  $imageRules
     * @return array<int, mixed>
     */
    private static function imageFieldRules(?Store $store, string $pathColumn, array $imageRules, bool $requiredWhenMissing = true): array
    {
        if ($store === null) {
            return array_merge([$requiredWhenMissing ? 'required' : 'nullable'], $imageRules);
        }

        return array_merge([
            Rule::requiredIf(fn () => $requiredWhenMissing && blank($store->{$pathColumn})),
            'nullable',
        ], $imageRules);
    }
}
