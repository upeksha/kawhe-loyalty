@props(['store', 'program'])
@php
    $theme = \App\Support\ProgramBranding::resolve($program, $store);
    $logo = $program->pass_logo_url ?? $program->logo_url ?? $store->pass_logo_url ?? $store->logo_url;
    $photo = $program->card_design === 'photo'
        ? ($program->pass_hero_image_url ?? $store->pass_hero_image_url)
        : null;
@endphp
<aside aria-label="Design preview for {{ $program->name }}" class="relative flex h-40 w-full max-w-[220px] flex-col justify-between overflow-hidden rounded-2xl p-4 shadow-sm" style="background-color: {{ $theme->bg }}; color: {{ $photo ? '#ffffff' : $theme->textOnBg }}">
    @if($photo)
        <img src="{{ $photo }}" alt="" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-black/60"></div>
    @endif
    <div class="relative flex items-start justify-between gap-2">
        @if($logo)
            <img src="{{ $logo }}" alt="{{ $store->name }} logo" class="max-h-9 max-w-20 object-contain object-left">
        @else
            <span class="text-xs font-semibold uppercase tracking-wide">{{ $store->name }}</span>
        @endif
        <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide opacity-80">Card preview</span>
    </div>
    <div class="relative min-w-0">
        <p class="text-[10px] font-semibold uppercase tracking-wider opacity-80">Your reward</p>
        <p class="mt-1 max-h-12 overflow-hidden break-words text-lg font-semibold leading-6">{{ $program->reward_title }}</p>
    </div>
</aside>
