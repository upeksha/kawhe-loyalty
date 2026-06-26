@props([
    'stores' => [],
    'limit' => null,
    'isSubscribed' => false,
    'compact' => false,
    'canCreateProgram' => false,
])

@php
    $stores = collect($stores);
    $totalUsed = (int) $stores->sum('programs_count');
    $storeCount = $stores->count();
    $totalCapacity = ($limit !== null && $storeCount > 0) ? $storeCount * $limit : null;
    $storesWithRoom = $stores->filter(fn ($store) => (bool) ($store['can_create_program'] ?? false))->count();
    $storesAtMax = $storeCount - $storesWithRoom;
    $allStoresFull = $storeCount > 0 && $storesWithRoom === 0;
    $limitLabel = $limit ? "Up to {$limit} cards per store" : 'Unlimited cards per store';

    $status = match (true) {
        $stores->isEmpty() => 'No stores yet',
        $canCreateProgram && $storesWithRoom > 0 => $storesWithRoom === 1
            ? 'Room for more cards on 1 store'
            : "Room for more cards on {$storesWithRoom} stores",
        $canCreateProgram => 'Can add cards on a new store',
        $allStoresFull && $isSubscribed => "All {$storeCount} store".($storeCount === 1 ? '' : 's')." at the {$limit}-card Pro limit",
        $allStoresFull => 'Card limit reached on every store',
        default => $limitLabel,
    };

    $atTotalCapacity = $totalCapacity !== null && $totalUsed >= $totalCapacity;
@endphp

<div {{ $attributes }}>
    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">
        Cards
    </p>
    <p class="mt-2 {{ $compact ? 'text-lg' : 'text-xl' }} font-semibold text-stone-900">
        {{ $totalUsed }}
        @if($totalCapacity)
            <span class="{{ $compact ? 'text-sm' : 'text-base' }} font-medium text-stone-500">/ {{ $totalCapacity }}</span>
        @elseif($limit)
            <span class="{{ $compact ? 'text-sm' : 'text-base' }} font-medium text-stone-500">across {{ $storeCount }} {{ Str::plural('store', $storeCount) }}</span>
        @endif
    </p>
    <p class="mt-1 {{ $compact ? 'text-xs' : 'text-sm' }} text-stone-600">{{ $status }}</p>

    @if($stores->isNotEmpty() && $totalCapacity)
        <x-ui.usage-meter
            class="mt-3"
            :used="$totalUsed"
            :limit="$totalCapacity"
            :show-percent="! $compact"
            :full-label="$isSubscribed && $atTotalCapacity ? 'Maximum' : 'At limit'"
            :full-tone="$isSubscribed && $atTotalCapacity ? 'neutral' : 'danger'"
        />
    @endif

    @if($stores->isNotEmpty())
        <ul class="mt-3 space-y-1.5 {{ $compact ? 'text-[11px]' : 'text-xs' }} text-stone-600">
            @foreach($stores as $storeUsage)
                @php
                    $used = (int) ($storeUsage['programs_count'] ?? 0);
                @endphp
                <li class="flex items-center justify-between gap-2">
                    <span class="truncate">{{ $storeUsage['store_name'] }}</span>
                    <span class="shrink-0 font-medium text-stone-700">
                        {{ $used }}@if($limit)<span class="text-stone-500"> / {{ $limit }}</span>@endif
                    </span>
                </li>
            @endforeach
        </ul>
        <p class="mt-2 {{ $compact ? 'text-[11px]' : 'text-xs' }} text-stone-500">{{ $limitLabel }}</p>
    @endif
</div>
