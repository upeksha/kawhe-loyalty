<x-guest-layout>
    <h1 class="text-xl font-bold text-stone-900 mb-1">Reset your password</h1>
    <p class="text-sm text-stone-500 mb-6">
        Enter your email and we'll send you a link to reset your password.
    </p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-stone-700 mb-1.5">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition"
                placeholder="you@example.com">
            <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-sm" />
        </div>

        <button type="submit"
            class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-xl px-4 py-3 text-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
            Send reset link
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-stone-500">
        Remembered it?
        <a href="{{ route('login') }}" class="text-brand-600 hover:text-brand-700 font-medium">Back to sign in</a>
    </p>
</x-guest-layout>
