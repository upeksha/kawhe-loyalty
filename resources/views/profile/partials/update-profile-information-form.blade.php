<section>
    <header class="sr-only">
        <h2 class="text-lg font-medium text-stone-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-stone-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" aria-describedby="email-verification-status" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div id="email-verification-status">
                    <p class="text-sm mt-2 text-stone-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" type="submit" class="mt-3 flex min-h-12 w-full items-center justify-center rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white hover:bg-brand-700">
                            {{ __('Resend verification email') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-brand-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @else
                <p id="email-verification-status" class="mt-2 text-sm text-brand-700">Email verified</p>
            @endif
        </div>

        <div class="space-y-3">
            <x-ui.button type="submit" variant="primary" class="w-full">
                {{ __('Save profile') }}
            </x-ui.button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-stone-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
