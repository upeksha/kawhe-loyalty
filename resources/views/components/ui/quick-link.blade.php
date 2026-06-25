@props([
    'label',
    'title',
    'hover' => 'brand',
])

@php
    $hoverClasses = [
        'brand' => 'hover:border-brand-200 hover:bg-brand-50/60',
        'accent' => 'hover:border-accent-200 hover:bg-accent-50/50',
        'emerald' => 'hover:border-emerald-200 hover:bg-emerald-50/50',
    ];
@endphp

<a {{ $attributes->merge(['class' => 'rounded-2xl border border-stone-200 bg-stone-50 px-4 py-4 transition '.($hoverClasses[$hover] ?? $hoverClasses['brand'])]) }}>
    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">{{ $label }}</p>
    <p class="mt-2 text-sm font-semibold text-stone-900">{{ $title }}</p>
</a>
