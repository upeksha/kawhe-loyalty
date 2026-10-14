@php
    $cardTitle = \App\Support\ProgramBranding::cardTitle($program, $store);
    $formConfig = \App\Support\RegistrationFormConfig::normalize($program->registration_form_config ?? []);
@endphp

<header class="mb-5">
    <div class="mb-3 flex items-center gap-3">
        @if($program->logo_path)<img src="{{ $program->logo_url }}" alt="" class="h-12 w-12 rounded-lg object-contain">@endif
        <p class="customer-card-title font-semibold">{{ $store->name }}</p>
    </div>
    <h1 class="customer-page-heading">Get my card</h1>
    @if($cardTitle !== $program->reward_title && $cardTitle !== $store->name)<p class="customer-page-description">{{ $cardTitle }}</p>@endif
    <p class="customer-page-description">Collect {{ $program->reward_target }} stamps to earn {{ $program->reward_title }}.</p>
    <p class="mt-3 text-sm customer-muted">No password. No customer app required.</p>
</header>

@if($showSavedCard ?? false)
    <template x-if="lastToken">
        <div class="mb-5 rounded-xl border border-stone-200 bg-white p-4">
            <p class="mb-3 text-sm customer-card-body">Already joined? A card is saved on this device.</p>
            <a :href="'/c/' + lastToken" class="customer-btn customer-btn-secondary">Open my card</a>
            <button type="button" @click="clearLastCard()" class="mt-3 min-h-11 w-full text-sm font-medium text-brand-700 underline">Use a different card or email</button>
        </div>
    </template>
@endif

<form method="POST" action="{{ route('join.store', ['slug' => $program->slug, 't' => $token]) }}" class="customer-form" id="join-form">
    @csrf
    <x-form-error-summary form-id="join-form" />
    <div class="customer-field">
        <label for="email" class="customer-card-label mb-1.5 block text-sm font-medium">Email address</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" class="customer-input" placeholder="you@example.com" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="255" aria-describedby="email-help" required>
        <p id="email-help" class="mt-2 text-xs customer-muted">Use an email you can open. It helps you recover your card and confirm rewards.</p>
        <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-sm" />
    </div>

    @include('join.partials.fields')

    <div>
        <button type="submit" class="customer-btn customer-btn-primary" data-loading-text="Creating your card…">Create my card</button>
        @if($program->require_verification_for_redemption)
            <p class="mt-3 text-xs leading-relaxed customer-muted">Start collecting stamps straight away. Confirm your email before redeeming a reward.</p>
        @endif
    </div>
</form>

<div class="mt-5 text-center">
    <a href="{{ route('join.existing', ['slug' => $program->slug, 't' => $token]) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-700 underline">Recover an existing card</a>
</div>

    @push('scripts')
        <script>
            function prefillEmail() {
                try {
                    var savedEmail = localStorage.getItem('kawhe_last_email_{{ $program->id }}')@if($program->is_default ?? false)
                        || localStorage.getItem('kawhe_last_email_{{ $store->id }}')@endif;
                    var hasOldEmail = @json(session()->hasOldInput('email'));
                    if (savedEmail && !hasOldEmail) {
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
