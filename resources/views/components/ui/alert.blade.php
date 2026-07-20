@props([
    'variant' => 'info',
])

@php
    $variantClasses = [
        'info' => 'border-blue-200 bg-blue-50 text-blue-900',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'danger' => 'border-red-200 bg-red-50 text-red-900',
        'processing' => 'border-brand-200 bg-brand-50 text-brand-900',
    ];
@endphp

<div
    role="alert"
    aria-live="{{ in_array($variant, ['danger', 'warning'], true) ? 'assertive' : 'polite' }}"
    {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 text-sm '.($variantClasses[$variant] ?? $variantClasses['info'])]) }}
>
    {{ $slot }}
</div>
