@props([
    'variant' => 'default', // default, success, warning, danger, info
])

@php
    $variantClasses = [
        'default' => 'bg-stone-100 text-stone-800',
        'success' => 'bg-green-100 text-green-800',
        'warning' => 'bg-accent-100 text-accent-800',
        'danger' => 'bg-red-100 text-red-800',
        'info' => 'bg-brand-100 text-brand-800',
    ];
    
    $classes = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $variantClasses[$variant];
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
