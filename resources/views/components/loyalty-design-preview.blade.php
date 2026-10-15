@props(['store', 'program'])
@php
    $target = max(1, (int) $program->reward_target);
    $stamps = min(4, $target - 1);
    $theme = \App\Support\ProgramBranding::resolve($program, $store);
    $background = $theme->bg;
    $ink = $theme->textOnBg;
@endphp
<aside aria-label="Sample card preview for {{ $program->name }}" class="w-full max-w-[240px] space-y-3 rounded-2xl p-4" style="background-color: {{ $background }}; color: {{ $ink }}">
    <p class="break-words text-sm font-semibold">{{ $store->name }}</p>
    <p class="break-words text-base font-semibold">{{ $program->reward_title }}</p>
    <p class="text-xl font-semibold">{{ $stamps }} of {{ $target }} stamps</p>
    <p class="text-xs">{{ $target - $stamps }} more stamps to your next reward</p>
    <div class="flex flex-wrap gap-1.5" aria-hidden="true">
        @for($i=0; $i < min($target, 20); $i++)
            <span class="h-6 w-6 shrink-0 rounded-full border-2" style="border-color: {{ $ink }}; background-color: {{ $i < $stamps ? $ink : 'transparent' }}"></span>
        @endfor
        @if($target > 20)<span class="text-xs">+{{ $target - 20 }}</span>@endif
    </div>
    <div class="space-y-2 rounded-xl bg-white p-3 text-center text-stone-600">
        <img src="{{ asset('images/loyalty-card-demo-qr.svg') }}" width="100" height="100" alt="Non-scannable customer preview" class="mx-auto h-[100px] w-[100px]">
        <p class="text-xs">Demo card · not for scanning</p>
    </div>
    <p class="text-xs">Sample progress only · no customer account</p>
</aside>
