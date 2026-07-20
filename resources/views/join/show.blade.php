@php
    use App\Support\ProgramBranding;

    $cardTitle = ProgramBranding::cardTitle($program, $store);
    $cardSubtitle = ProgramBranding::cardSubtitle($program, $store);
    $formConfig = $program->registration_form_config;
@endphp

<x-customer-layout :program="$program" :store="$store" title="Get your card">
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
        <h2 class="customer-card-title text-center text-xl sm:text-2xl font-bold mb-1">{{ $cardTitle }}</h2>
        @if($cardSubtitle)
            <p class="customer-card-body text-center text-sm mb-2">{{ $cardSubtitle }}</p>
        @endif
        <p class="customer-card-body text-center text-sm sm:text-base mb-6">
            Collect {{ $program->reward_target }} stamps to earn {{ $program->reward_title }}.
        </p>

        <form method="POST" action="{{ route('join.store', ['slug' => $program->slug, 't' => $token]) }}" class="space-y-4 sm:space-y-5" id="join-form">
            @csrf
            <x-form-error-summary form-id="join-form" />

            <div>
                <label for="email" class="customer-card-label mb-1.5 block text-sm font-medium">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="customer-input" placeholder="you@example.com" autocomplete="email" inputmode="email" required>
                <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-sm" />
            </div>

            @if(!empty($formConfig['first_name']['enabled']))
            <div>
                <label for="first_name" class="customer-card-label mb-1.5 block text-sm font-medium">First name{{ !empty($formConfig['first_name']['required']) ? '' : ' (optional)' }}</label>
                <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" class="customer-input" placeholder="First name" autocomplete="given-name" {{ !empty($formConfig['first_name']['required']) ? 'required' : '' }}>
                <x-input-error :messages="$errors->get('first_name')" class="mt-1.5 text-sm" />
            </div>
            @endif

            @if(!empty($formConfig['last_name']['enabled']))
            <div>
                <label for="last_name" class="customer-card-label mb-1.5 block text-sm font-medium">Last name{{ !empty($formConfig['last_name']['required']) ? '' : ' (optional)' }}</label>
                <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" class="customer-input" placeholder="Last name" autocomplete="family-name" {{ !empty($formConfig['last_name']['required']) ? 'required' : '' }}>
                <x-input-error :messages="$errors->get('last_name')" class="mt-1.5 text-sm" />
            </div>
            @endif

            @if(!empty($formConfig['phone']['enabled']))
            <div>
                <label for="phone" class="customer-card-label mb-1.5 block text-sm font-medium">Phone{{ !empty($formConfig['phone']['required']) ? '' : ' (optional)' }}</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" class="customer-input" placeholder="Phone number" autocomplete="tel" inputmode="tel" {{ !empty($formConfig['phone']['required']) ? 'required' : '' }}>
                <x-input-error :messages="$errors->get('phone')" class="mt-1.5 text-sm" />
            </div>
            @endif

            @if(!empty($formConfig['birthday']['enabled']))
            <div>
                <label for="birthday" class="customer-card-label mb-1.5 block text-sm font-medium">Birthday{{ !empty($formConfig['birthday']['required']) ? '' : ' (optional)' }}</label>
                <input type="date" id="birthday" name="birthday" value="{{ old('birthday') }}" class="customer-input" autocomplete="bday" {{ !empty($formConfig['birthday']['required']) ? 'required' : '' }}>
                <x-input-error :messages="$errors->get('birthday')" class="mt-1.5 text-sm" />
            </div>
            @endif

            <div class="pt-1">
                <button type="submit" class="customer-btn customer-btn-primary" data-loading-text="Creating your card…">
                    Join loyalty card
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
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
