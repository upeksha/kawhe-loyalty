<x-customer-layout :program="$program" :store="$store" title="Get your card" x-data="joinLanding()">
    @include('join.partials.signup', ['showSavedCard' => true])
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
