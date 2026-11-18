<x-guest-layout>
    <x-slot:merchantPreview><x-merchant-account-preview /></x-slot:merchantPreview>
    <h1 class="kawhe-setup-title mb-2 text-2xl font-bold tracking-tight sm:text-3xl">Sign in to Kawhe</h1>
    <p class="mb-6 text-sm text-stone-600">Use the email and password for your merchant account.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5" id="login-form">
        @csrf
        <x-form-error-summary form-id="login-form" max-items="3" />

        <div>
            <label for="email" class="block text-sm font-medium text-stone-700 mb-1.5">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                @error('email') aria-invalid="true" @enderror
                class="w-full rounded-xl border bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 transition @error('email') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:ring-brand-500/30 focus:border-brand-500 @enderror">
            <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-sm" />
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-sm font-medium text-stone-700">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs text-brand-600 hover:text-brand-700 font-medium">
                        Forgot password?
                    </a>
                @endif
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                @error('password') aria-invalid="true" @enderror
                class="w-full rounded-xl border bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 transition @error('password') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:ring-brand-500/30 focus:border-brand-500 @enderror">
            <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-sm" />
        </div>

        <div class="flex items-center gap-2">
            <input id="remember_me" type="checkbox" name="remember"
                class="rounded border-stone-300 text-brand-600 shadow-sm focus:ring-brand-500">
            <label for="remember_me" class="text-sm text-stone-600">Remember me</label>
        </div>

        @if(Route::has('register'))
            <div class="rounded-xl border border-brand-100 bg-brand-50 p-4 text-sm">
                <p class="font-semibold text-brand-900">New to Kawhe?</p>
                <p class="mt-1 text-stone-600">Create your café account to set up your first loyalty card.</p>
            </div>
        @endif
        <div class="flex flex-wrap items-center gap-3 border-t border-stone-200 pt-4">
            <x-ui.button type="submit" variant="primary" data-loading-text="Signing in...">Sign in</x-ui.button>
            @if(Route::has('register'))
                <x-ui.button :href="route('register')" variant="secondary">Create an account</x-ui.button>
            @endif
        </div>
    </form>
</x-guest-layout>
