@php
    use App\Support\ProgramBranding;

    $cardTitle = ProgramBranding::cardTitle($program, $store);
    $cardSubtitle = ProgramBranding::cardSubtitle($program, $store);
@endphp

<x-customer-layout :program="$program" :store="$store" :title="$cardTitle" centered x-data="joinLanding()">
    <x-slot name="hero">
        <div class="text-center mb-6 sm:mb-8">
            @if($program->logo_path)
                <img src="{{ $program->logo_url }}" alt="{{ $cardTitle }}" class="mx-auto h-16 w-auto object-contain sm:h-20" style="max-height: 5rem;">
            @else
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight">{{ $cardTitle }}</h1>
            @endif
            @if($cardSubtitle)
                <p class="mt-1 text-sm sm:text-base customer-muted">{{ $cardSubtitle }}</p>
            @endif
            <p class="mt-2 sm:mt-3 text-sm sm:text-base customer-muted">
                Collect {{ $program->reward_target }} stamps to earn {{ strtolower($program->reward_title ?: 'a reward') }}.
            </p>
        </div>
    </x-slot>

    <div class="customer-card customer-card--gradient p-6 sm:p-8 w-full">
        <div class="space-y-4 sm:space-y-5">
            <template x-if="lastToken">
                <div>
                    <a :href="'/c/' + lastToken" class="customer-btn customer-btn-primary">
                        Open my card
                    </a>
                    <p class="customer-card-body mt-2 text-center text-xs sm:text-sm">
                        We found a card saved on this device.
                    </p>
                </div>
            </template>

            <div>
                <a href="{{ route('join.show', ['slug' => $program->slug, 't' => $token]) }}" class="customer-btn customer-btn-primary">
                    Get my loyalty card
                </a>
                <p class="customer-card-body mt-2 text-center text-xs sm:text-sm">
                    Takes under a minute. Add to Apple Wallet or Google Wallet after you join.
                </p>
            </div>

            <div>
                <a href="{{ route('join.existing', ['slug' => $program->slug, 't' => $token]) }}" class="customer-btn customer-btn-secondary">
                    I already joined
                </a>
                <p class="customer-card-body mt-2 text-center text-xs">
                    Use the same email you signed up with for this card.
                </p>
            </div>

            <template x-if="lastToken">
                <div class="text-center pt-1">
                    <button type="button" @click="clearLastCard()" class="customer-card-body text-xs sm:text-sm underline hover:opacity-80">
                        Use a different card or email
                    </button>
                </div>
            </template>
        </div>
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
