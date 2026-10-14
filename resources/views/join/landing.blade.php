<x-customer-layout :program="$program" :store="$store" title="Get your card" x-data="joinLanding()">
    @include('join.partials.signup', ['showSavedCard' => true])
    <details class="mt-4">
        <summary class="min-h-11 cursor-pointer text-sm font-semibold customer-card-title">Preview this loyalty card</summary>
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
    </div>
    </details>
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('joinLanding', () => ({
                    lastToken: null,
                    init() {
                        try { this.lastToken = localStorage.getItem('kawhe_last_card_{{ $program->id }}')@if($program->is_default ?? false)
                        || localStorage.getItem('kawhe_last_card_{{ $store->id }}')@endif; } catch (e) {}
                    },
                    clearLastCard() {
                        try { localStorage.removeItem('kawhe_last_card_{{ $program->id }}');
                        localStorage.removeItem('kawhe_last_email_{{ $program->id }}');
                        @if($program->is_default ?? false)
                        localStorage.removeItem('kawhe_last_card_{{ $store->id }}');
                        localStorage.removeItem('kawhe_last_email_{{ $store->id }}');
                        @endif
                        } catch (e) {}
                        this.lastToken = null;
                        const email = document.getElementById('email');
                        if (email) { email.value = ''; email.focus(); }
                    }
                }));
            });
        </script>
    @endpush
</x-customer-layout>
