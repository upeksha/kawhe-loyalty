@props(['disabled' => false])

@php
    $name = $attributes->get('name');
    $nameKey = is_string($name) ? rtrim($name, '[]') : null;
    $hasError = $nameKey && $errors->has($nameKey);
    $baseClasses = 'rounded-xl border bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 transition disabled:opacity-60';
    $stateClasses = $hasError
        ? 'border-red-300 focus:border-red-500 focus:ring-red-500/30'
        : 'border-stone-300 focus:border-brand-500 focus:ring-brand-500/30';
@endphp

<input
    @disabled($disabled)
    @if($hasError) aria-invalid="true" @endif
    {{ $attributes->merge(['class' => $baseClasses . ' ' . $stateClasses]) }}
>
