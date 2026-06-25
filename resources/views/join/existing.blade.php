@php
    use App\Support\ProgramBranding;

    $cardTitle = ProgramBranding::cardTitle($program, $store);
    $cardSubtitle = ProgramBranding::cardSubtitle($program, $store);
@endphp

<x-customer-layout :program="$program" :store="$store" title="Find my card">
    <x-slot name="back">
        <a href="{{ route('join.index', ['slug' => $program->slug, 't' => $token]) }}" class="inline-flex items-center text-sm customer-muted hover:opacity-90">
            <svg class="mr-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back
        </a>
    </x-slot>

    <div class="customer-card customer-card--gradient p-6 sm:p-8 w-full">
        @if($program->logo_path)
            <img src="{{ $program->logo_url }}" alt="{{ $program->name }}" class="mx-auto mb-4 h-12 w-auto object-contain sm:h-14">
        @endif

        <h2 class="customer-card-title text-center text-xl sm:text-2xl font-bold mb-1">Find my card</h2>
        @if($cardSubtitle)
            <p class="customer-card-body text-center text-sm mb-2">{{ $cardSubtitle }}</p>
        @endif
        <p class="customer-card-body text-center text-sm sm:text-base mb-6">
            @if($phoneLookupEnabled ?? false)
                Enter the email or phone number you used to join <strong class="customer-card-title">{{ $cardTitle }}</strong>.
            @else
                Enter the email you used to join <strong class="customer-card-title">{{ $cardTitle }}</strong>.
            @endif
        </p>

        <form action="{{ route('join.lookup', ['slug' => $program->slug, 't' => $token]) }}" method="POST" class="space-y-4 sm:space-y-5" id="lookup-form">
            @csrf
            <div>
                <label for="email" class="customer-card-label mb-1.5 block text-sm font-medium">
                    Email address
                    @if($phoneLookupEnabled ?? false)
                        <span class="font-normal opacity-70">(optional if you use phone)</span>
                    @endif
                </label>
                <input
                    id="email" name="email" type="email" autocomplete="email"
                    @unless($phoneLookupEnabled ?? false) required @endunless
                    value="{{ old('email') }}"
                    placeholder="you@example.com"
                    class="customer-input"
                >
                <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-sm" />
            </div>

            @if($phoneLookupEnabled ?? false)
            <div>
                <label for="phone" class="customer-card-label mb-1.5 block text-sm font-medium">
                    Phone number
                    <span class="font-normal opacity-70">(optional if you use email)</span>
                </label>
                <input
                    id="phone" name="phone" type="tel" autocomplete="tel"
                    value="{{ old('phone') }}"
                    placeholder="e.g. 04 1234 5678"
                    class="customer-input"
                >
                <x-input-error :messages="$errors->get('phone')" class="mt-1.5 text-sm" />
            </div>
            @endif

            <div class="pt-1">
                <button type="submit" class="customer-btn customer-btn-primary" data-loading-text="Opening your card...">
                    Open my card
                </button>
            </div>
        </form>

        <div class="customer-divider mt-6 pt-5 text-center">
            <p class="customer-card-body text-sm mb-3">Don't have a card yet?</p>
            <a href="{{ route('join.show', ['slug' => $program->slug, 't' => $token]) }}" class="customer-btn customer-btn-outline">
                Create a new card
            </a>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('lookup-form')?.addEventListener('submit', function () {
                const btn = this.querySelector('[type="submit"]');
                if (btn && !btn.disabled) {
                    btn.disabled = true;
                    btn.textContent = btn.dataset.loadingText || 'Please wait...';
                }
            });
            function prefillEmail() {
                try {
                    var savedEmail = localStorage.getItem('kawhe_last_email_{{ $program->id }}')@if($program->is_default ?? false)
                        || localStorage.getItem('kawhe_last_email_{{ $store->id }}')@endif;
                    var oldEmail = @json(old('email'));
                    if (savedEmail && !oldEmail) {
                        var emailInput = document.getElementById('email');
                        if (emailInput && !emailInput.value) {
                            emailInput.value = savedEmail;
                            emailInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }
                } catch (e) {}
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', prefillEmail);
            } else {
                prefillEmail();
            }
            setTimeout(prefillEmail, 100);
            window.addEventListener('load', prefillEmail);
        </script>
    @endpush
</x-customer-layout>
