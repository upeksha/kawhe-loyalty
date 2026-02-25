<x-guest-layout>
    <h1 class="text-xl font-bold text-stone-900 mb-1">Create your account</h1>
    <p class="text-sm text-stone-500 mb-6">Get started with your loyalty program</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-stone-700 mb-1.5">Full name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition"
                placeholder="Your name">
            <x-input-error :messages="$errors->get('name')" class="mt-1.5 text-sm" />
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-stone-700 mb-1.5">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition"
                placeholder="you@example.com">
            <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-sm" />
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-stone-700 mb-1.5">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition">
            <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-sm" />
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-stone-700 mb-1.5">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition">
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5 text-sm" />
        </div>

        <button type="submit"
            class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-xl px-4 py-3 text-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
            Create account
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-stone-500">
        Already have an account?
        <a href="{{ route('login') }}" class="text-brand-600 hover:text-brand-700 font-medium">Sign in</a>
    </p>
</x-guest-layout>
