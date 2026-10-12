<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Verify your email before using your merchant dashboard, stores, or scanner. Open the verification email, or request a new link below. Check your spam folder too.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A verification email has been queued for your current email address. Check your inbox and spam folder shortly.') }}
        </div>
    @endif

    <x-input-error :messages="$errors->get('verification')" class="mb-4" />
    @if (session('status') === 'verification-link-failed')
        <p class="mb-4 text-sm text-red-600">Your account was created, but we could not queue the verification email. Please use the resend button to try again.</p>
    @endif

    <p class="mb-4 text-sm text-gray-600">Verification email: {{ auth()->user()->email }}</p>
    <div class="mb-4 flex gap-4 text-sm">
        <a class="underline" href="{{ route('profile.edit') }}">Change email or manage account</a>
        @unless(auth()->user()->is_staff_account)<a class="underline" href="{{ route('billing.index') }}">Manage billing</a>@endunless
    </div>

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
