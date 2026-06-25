<?php

namespace App\Support;

use Illuminate\Http\Request;

class RegistrationFormConfig
{
    public const OPTIONAL_FIELDS = ['first_name', 'last_name', 'phone', 'birthday'];

    /**
     * @return array<string, array{label: string, placeholder: string}>
     */
    public static function fieldDefinitions(): array
    {
        return [
            'first_name' => ['label' => 'First name', 'placeholder' => 'e.g. Jane'],
            'last_name' => ['label' => 'Last name', 'placeholder' => 'e.g. Smith'],
            'phone' => ['label' => 'Phone', 'placeholder' => 'e.g. 04 1234 5678'],
            'birthday' => ['label' => 'Birthday', 'placeholder' => 'DD/MM/YYYY'],
        ];
    }

    /**
     * @return array<string, array{enabled: bool, required: bool}>
     */
    public static function fromRequest(Request $request): array
    {
        $config = ['email' => ['enabled' => true, 'required' => true]];

        foreach (self::OPTIONAL_FIELDS as $field) {
            $config[$field] = [
                'enabled' => $request->boolean("{$field}_enabled"),
                'required' => $request->boolean("{$field}_required"),
            ];
        }

        return self::normalize($config);
    }

    /**
     * @param  array<string, array{enabled?: bool, required?: bool}>  $config
     * @return array<string, array{enabled: bool, required: bool}>
     */
    public static function normalize(array $config): array
    {
        $normalized = array_merge(
            ['email' => ['enabled' => true, 'required' => true]],
            $config
        );

        foreach (self::OPTIONAL_FIELDS as $field) {
            $normalized[$field]['enabled'] = (bool) ($normalized[$field]['enabled'] ?? false);
            $normalized[$field]['required'] = (bool) ($normalized[$field]['required'] ?? false);

            if (! $normalized[$field]['enabled']) {
                $normalized[$field]['required'] = false;
            }
        }

        $normalized['email'] = ['enabled' => true, 'required' => true];

        return $normalized;
    }

    /**
     * @param  array<string, array{enabled?: bool, required?: bool}>  $config
     */
    public static function phoneLookupEnabled(array $config): bool
    {
        return ! empty($config['phone']['enabled']);
    }
}
