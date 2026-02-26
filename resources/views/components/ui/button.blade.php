@props([
    'variant' => 'primary', // primary, secondary, danger, ghost
    'size' => 'md', // sm, md, lg
    'type' => 'button',
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 font-semibold rounded-xl transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';
    
    $variantClasses = [
        'primary' => 'bg-brand-600 text-white hover:bg-brand-700 focus:ring-brand-500 shadow-sm',
        'secondary' => 'bg-stone-100 text-stone-900 hover:bg-stone-200 focus:ring-stone-500 border border-stone-200',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 shadow-sm',
        'ghost' => 'bg-transparent text-stone-700 hover:bg-stone-100 focus:ring-stone-500',
    ];
    
    $sizeClasses = [
        'sm' => 'px-3 py-2 text-sm leading-5',
        'md' => 'px-4 py-2.5 text-sm leading-5',
        'lg' => 'px-6 py-3 text-base leading-6',
    ];
    
    $classes = $baseClasses . ' ' . ($variantClasses[$variant] ?? $variantClasses['primary']) . ' ' . ($sizeClasses[$size] ?? $sizeClasses['md']);
@endphp

@if(isset($attributes['href']))
    <a {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
