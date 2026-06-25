@php
    use App\Support\ProgramBranding;

    $cardTitle = ProgramBranding::cardTitle($program, $store);
@endphp

<x-customer-layout :program="$program" :store="$store" title="Link not valid" centered>
    <x-slot name="hero">
        <div class="mb-6 text-center">
            @if($program?->logo_path)
                <img src="{{ $program->logo_url }}" alt="{{ $cardTitle }}" class="mx-auto mb-4 h-14 w-auto object-contain">
            @endif
        </div>
    </x-slot>

    <div class="customer-card customer-card--plain p-6 sm:p-8 w-full text-center">
        <div class="customer-icon-badge mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
        </div>

        <h1 class="mb-2 text-xl sm:text-2xl font-bold">This link isn’t valid</h1>
        <p class="mb-2 text-sm sm:text-base text-stone-600">The loyalty card link may be outdated, incomplete, or typed incorrectly.</p>
        <p class="mb-6 text-sm text-stone-500">
            Ask staff for a fresh QR code or join link.
            @if ($store)
                <span> Visit <strong>{{ $store->name }}</strong> if you are nearby.</span>
            @endif
        </p>

        @if($store && $program)
            <p class="text-xs opacity-70">
                If you already joined, use the email lookup from the store’s current join page.
            </p>
        @else
            <p class="text-xs opacity-70">
                Double-check the full URL — join links include a security token at the end.
            </p>
        @endif
    </div>
</x-customer-layout>
