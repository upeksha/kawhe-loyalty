@props([
    'variant' => 'default', // default, success, warning, danger, info
])

@php
    $variantClasses = [
        'default' => 'bg-stone-100 text-stone-700 border border-stone-200',
        'success' => 'bg-green-100 text-green-800 border border-green-200',
        'warning' => 'bg-accent-100 text-accent-800 border border-accent-200',
        'danger' => 'bg-red-100 text-red-800 border border-red-200',
        'info' => 'bg-brand-100 text-brand-800 border border-brand-200',
    ];
    
    $classes = 'inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold tracking-wide ' . ($variantClasses[$variant] ?? $variantClasses['default']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
