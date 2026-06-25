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

        return [
            'brand_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => self::imageFieldRules($store, 'logo_path', $imageRules),
            'pass_logo' => self::imageFieldRules($store, 'pass_logo_path', $imageRules),
            'pass_hero_image' => self::imageFieldRules($store, 'pass_hero_image_path', $imageRules),
        ];
    }

    /**
     * @param  array<int, string>  $imageRules
     * @return array<int, mixed>
     */
    private static function imageFieldRules(?Store $store, string $pathColumn, array $imageRules): array
    {
        if ($store === null) {
            return array_merge(['required'], $imageRules);
        }

        return array_merge([
            Rule::requiredIf(fn () => blank($store->{$pathColumn})),
            'nullable',
        ], $imageRules);
    }
}
