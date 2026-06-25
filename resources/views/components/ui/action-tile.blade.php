@props([
    'label',
    'tone' => 'sand',
])

@php
    $tones = [
        'sand' => 'bg-[#f3efe7] text-[#3a2a22] hover:bg-[#ece4d6]',
        'sage' => 'bg-[#edf4eb] text-[#1f3b2c] hover:bg-[#deebda]',
        'peach' => 'bg-[#fff4e9] text-[#c96a3b] hover:bg-[#fde8d7]',
        'gold' => 'bg-[#fff5df] text-[#d6a24a] hover:bg-[#fcedcf]',
    ];
@endphp

<a {{ $attributes->merge(['class' => 'inline-flex w-full items-center justify-start rounded-xl px-3 py-3 text-sm font-semibold transition '.($tones[$tone] ?? $tones['sand'])]) }}>
    {{ $label }}
</a>
