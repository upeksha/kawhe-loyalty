@props([
    'variant' => 'default', // default, success, warning, danger, info
])

@php
    $variantClasses = [
        'default' => 'bg-stone-100 text-stone-700 border border-stone-200',
        'success' => 'bg-emerald-50 text-emerald-700 border border-emerald-100',
        'warning' => 'bg-amber-50 text-amber-700 border border-amber-100',
        'danger' => 'bg-red-50 text-red-700 border border-red-100',
        'info' => 'bg-brand-50 text-brand-700 border border-brand-100',
        'accent' => 'bg-accent-50 text-accent-700 border border-accent-100',
    ];
    
    $classes = 'inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold tracking-wide ' . ($variantClasses[$variant] ?? $variantClasses['default']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
