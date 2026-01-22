@props([
    'type' => 'text',
    'error' => false,
])

@php
    $baseClasses = 'block w-full rounded-lg border shadow-sm px-3 py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-0';
    $errorClasses = $error ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-stone-300 focus:border-brand-500 focus:ring-brand-500';
    $classes = $baseClasses . ' ' . $errorClasses . ' ' . ($attributes->get('class', ''));
@endphp

<input 
    type="{{ $type }}"
    {{ $attributes->merge(['class' => $classes]) }}
>
