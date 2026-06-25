@props([
    'label',
    'value',
    'caption' => null,
    'tone' => 'bg-stone-50',
    'accent' => 'text-stone-900',
    'size' => 'md',
    'layout' => 'vertical',
])

@php
    $valueSize = $size === 'lg' ? 'text-4xl font-semibold tracking-tight' : 'text-2xl font-bold';
@endphp

@if($layout === 'horizontal')
    <div {{ $attributes->merge(['class' => trim("rounded-xl p-5 {$tone}")]) }}>
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-stone-500">{{ $label }}</p>
                <p @class(['mt-4', $valueSize, $accent])>{{ $value }}</p>
                @if($caption)
                    <p class="mt-3 max-w-[16rem] text-sm leading-6 text-stone-600">{{ $caption }}</p>
                @endif
            </div>
            @isset($chart)
                <div class="mt-0 h-[5.5rem] w-36 shrink-0">
                    {{ $chart }}
                </div>
            @endisset
        </div>
        {{ $slot }}
    </div>
@else
    <div {{ $attributes->merge(['class' => trim("rounded-xl p-5 {$tone}")]) }}>
        <p class="text-sm font-medium text-stone-600">{{ $label }}</p>
        <p @class(['mt-1', $valueSize, $accent])>{{ $value }}</p>

        @if($caption)
            <p class="mt-3 max-w-[16rem] text-sm leading-6 text-stone-600">{{ $caption }}</p>
        @endif

        @isset($chart)
            <div class="mt-3">
                {{ $chart }}
            </div>
        @endisset

        {{ $slot }}
    </div>
@endif
