@props(['store' => null, 'program' => null])
@php
    $storeName = $store?->name;
    $logoUrl = $program?->logo_url ?? $store?->logo_url ?? $program?->pass_logo_url ?? $store?->pass_logo_url;
@endphp
<header class="mb-7 flex flex-col items-center gap-3 text-center" data-customer-store-brand>
    @if($storeName)
        @if($logoUrl)
            <div class="flex min-h-24 w-full max-w-60 items-center justify-center px-5 py-3">
                <img src="{{ $logoUrl }}" alt="{{ $storeName }} logo" class="h-20 w-full object-contain" onerror="this.parentElement.hidden = true; this.parentElement.nextElementSibling.hidden = false">
            </div>
        @endif
        <p @if($logoUrl) hidden @endif class="w-full break-words text-2xl font-semibold leading-tight text-stone-900">{{ $storeName }}</p>
    @else
        <p class="text-xl font-semibold text-stone-900">Loyalty card</p>
    @endif
</header>
