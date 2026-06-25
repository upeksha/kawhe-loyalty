@props([
    'used' => 0,
    'limit' => null,
    'showPercent' => true,
])

@php
    $hasLimit = $limit !== null && $limit > 0;
    $percent = $hasLimit ? min(100, max(0, ($used / $limit) * 100)) : null;
    $roundedPercent = $percent !== null ? (int) round($percent) : null;
    $barClass = match (true) {
        ! $hasLimit => 'bg-brand-400/35',
        $roundedPercent >= 100 => 'bg-red-500',
        $roundedPercent >= 80 => 'bg-amber-500',
        default => 'bg-brand-600',
    };
@endphp

<div {{ $attributes }}>
    @if($hasLimit)
        @if($showPercent)
            <div class="mb-1.5 flex items-center justify-between text-xs">
                <span class="text-stone-500">{{ $roundedPercent }}% used</span>
                @if($roundedPercent >= 100)
                    <span class="font-medium text-red-700">At limit</span>
                @elseif($roundedPercent >= 80)
                    <span class="font-medium text-amber-700">Nearly full</span>
                @endif
            </div>
        @endif
        <div class="h-2 w-full rounded-full bg-stone-200" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $limit }}" aria-valuenow="{{ $used }}" aria-label="{{ $roundedPercent }} percent used">
            <div class="h-2 rounded-full transition-all duration-300 {{ $barClass }}" style="width: {{ $roundedPercent }}%"></div>
        </div>
    @else
        <div class="flex items-center gap-2">
            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Unlimited</span>
            <span class="text-xs text-stone-500">No plan cap on this dimension</span>
        </div>
    @endif
</div>
