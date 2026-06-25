@props([
    'label',
    'value',
    'state' => 'neutral',
])

@php
    $stateClasses = [
        'neutral' => 'text-stone-700',
        'ready' => 'text-emerald-700',
        'attention' => 'text-amber-700',
        'danger' => 'text-red-700',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-3 rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3 text-sm']) }}>
    <span class="text-stone-700">{{ $label }}</span>
    @isset($status)
        <span class="font-medium">{{ $status }}</span>
    @else
        <span class="font-medium {{ $stateClasses[$state] ?? $stateClasses['neutral'] }}">{{ $value }}</span>
    @endisset
</div>
