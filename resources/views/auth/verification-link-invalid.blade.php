<x-guest-layout>
    <h1 class="mb-3 text-xl font-semibold text-gray-900">{{ $expired ? 'This verification link has expired' : 'This verification link is invalid' }}</h1>
    <p class="mb-5 text-sm text-gray-600">{{ $expired ? 'Verification links expire to keep your account secure. Request a new email below and use the newest link.' : 'This link may be incomplete or changed. Request a new email below and use the newest link.' }}</p>

    @auth
        @if(auth()->user()->hasVerifiedEmail())
            <p class="mb-4 text-sm text-gray-600">The account you are signed in to is already verified.</p>
            <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center font-semibold text-brand-700 underline">Continue to your account</a>
        @else
            @if(session('status') === 'verification-link-sent')
                <p role="status" class="mb-4 text-sm font-medium text-green-700">A new verification email has been queued. Check your inbox and junk folder.</p>
            @endif
            <x-input-error :messages="$errors->get('verification')" class="mb-4" />
            <p class="mb-4 break-words text-sm text-gray-600">Send to your signed-in account: <strong>{{ auth()->user()->email }}</strong></p>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-primary-button class="min-h-11">Resend verification email</x-primary-button>
            </form>
        @endif
        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="min-h-11 text-sm text-gray-600 underline">Sign out to use another account</button>
        </form>
    @else
        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center font-semibold text-brand-700 underline">Sign in to request a new link</a>
    @endauth
</x-guest-layout>
