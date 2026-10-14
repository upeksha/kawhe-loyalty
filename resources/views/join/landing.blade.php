@php
    use App\Support\ProgramBranding;

    $cardTitle = ProgramBranding::cardTitle($program, $store);
    $cardSubtitle = ProgramBranding::cardSubtitle($program, $store);
@endphp

<x-customer-layout :program="$program" :store="$store" :title="$cardTitle" centered x-data="joinLanding()">
    <x-slot name="hero">
        <header class="mb-6">
            <h1 class="customer-page-heading">{{ $cardTitle }}</h1>
            <p class="customer-page-description">{{ $store->name }} · Collect {{ $program->reward_target }} stamps for {{ strtolower($program->reward_title ?: 'a reward') }}.</p>
            @if($cardSubtitle && $cardSubtitle !== $store->name && $cardSubtitle !== $cardTitle)<p class="customer-page-description">{{ $cardSubtitle }}</p>@endif
        </header>
    </x-slot>

    <div class="loyalty-card customer-preview mb-6 p-6" aria-label="Loyalty card preview">
        <div class="flex items-center gap-3">
            @if($program->logo_path)<img src="{{ $program->logo_url }}" alt="{{ $store->name }} logo" class="h-12 w-12 rounded-lg object-contain">@endif
            <p class="card-text text-sm font-semibold">{{ $store->name }}</p>
        </div>
        <h2 class="card-text mt-6 text-xl font-semibold">{{ $program->reward_title }}</h2>
        <p class="card-text mt-6 text-[26px] font-semibold">0 of {{ $program->reward_target }} stamps</p>
        <p class="card-muted mt-2 text-xs">Join to start collecting</p>
        <div class="mt-6 flex flex-wrap gap-2" aria-hidden="true">
            @for($i = 0; $i < $program->reward_target; $i++)<span class="stamp-circle--empty h-9 w-9 rounded-full"></span>@endfor
        </div>
        <div class="customer-preview-qr mt-5 rounded-xl bg-white p-4 text-center">
            <img src="{{ asset('images/loyalty-card-demo-qr.svg') }}" width="164" height="164" class="mx-auto" alt="Example QR code, not a customer card">
            <p class="mt-3 text-xs text-stone-600">Card preview · Join to get your own QR code</p>
        </div>
    </div>

    <div class="customer-actions">
        <template x-if="lastToken">
            <div>
                <a :href="'/c/' + lastToken" class="customer-btn customer-btn-primary">Open my card</a>
                <p class="customer-card-body mt-2 text-center text-xs">We found a card saved on this device.</p>
            </div>
        </template>
        <div>
            <a href="{{ route('join.show', ['slug' => $program->slug, 't' => $token]) }}" class="customer-btn customer-btn-primary">Get my card</a>
            <p class="customer-card-body mt-2 text-center text-xs">Takes under a minute. No customer app required.</p>
        </div>
        <a href="{{ route('join.existing', ['slug' => $program->slug, 't' => $token]) }}" class="customer-btn customer-btn-secondary">Recover an existing card</a>
        <template x-if="lastToken">
            <button type="button" @click="clearLastCard()" class="customer-btn customer-btn-secondary">Use a different card or email</button>
        </template>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('joinLanding', () => ({
                    lastToken: localStorage.getItem('kawhe_last_card_{{ $program->id }}')@if($program->is_default ?? false)
                        || localStorage.getItem('kawhe_last_card_{{ $store->id }}')@endif,
                    clearLastCard() {
                        localStorage.removeItem('kawhe_last_card_{{ $program->id }}');
                        @if($program->is_default ?? false)
                        localStorage.removeItem('kawhe_last_card_{{ $store->id }}');
                        @endif
                        this.lastToken = null;
                    }
                }));
            });
        </script>
    @endpush
</x-customer-layout>
