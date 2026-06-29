@props([
    'label',
    'icon' => null,
    'tone' => 'sand',
])

@php
    $tones = [
        'sand' => 'bg-[#f3efe7] text-[#3a2a22] hover:bg-[#ece4d6]',
        'sage' => 'bg-[#edf4eb] text-[#1f3b2c] hover:bg-[#deebda]',
        'peach' => 'bg-[#fff4e9] text-[#c96a3b] hover:bg-[#fde8d7]',
        'gold' => 'bg-[#fff5df] text-[#d6a24a] hover:bg-[#fcedcf]',
    ];

    $icons = [
        'scanner' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2M9 9h6v6H9z" />',
        'qr' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2m4 0h-2m-4 4h6m-6-2h2m2 0h2m-4-4h2m2 0h2" />',
        'customers' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />',
    ];

    $iconPath = $icon ? ($icons[$icon] ?? null) : null;
@endphp

<a {{ $attributes->merge(['class' => 'inline-flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-2 py-2.5 text-center transition '.($tones[$tone] ?? $tones['sand'])]) }}>
    @if($iconPath)
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            {!! $iconPath !!}
        </svg>
    @endif
    <span class="text-[11px] font-semibold leading-tight">{{ $label }}</span>
</a>
