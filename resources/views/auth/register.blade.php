<x-guest-layout>
    <h1 class="text-xl font-bold text-stone-900 mb-1">Create your account</h1>
    <p class="text-sm text-stone-500 mb-6">Set up your account and first loyalty card</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-5" id="register-form">
        @csrf
        <x-form-error-summary form-id="register-form" max-items="3" />

        <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;">
            <label for="company_website">Leave this field empty</label>
            <input id="company_website" name="company_website" type="text" tabindex="-1" autocomplete="off" value="">
        </div>

        <div>
            <label for="name" class="block text-sm font-medium text-stone-700 mb-1.5">Full name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                @error('name') aria-invalid="true" @enderror
                class="w-full rounded-xl border bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 transition @error('name') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:ring-brand-500/30 focus:border-brand-500 @enderror"
                placeholder="Your name">
            <x-input-error :messages="$errors->get('name')" class="mt-1.5 text-sm" />
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-stone-700 mb-1.5">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                @error('email') aria-invalid="true" @enderror
                class="w-full rounded-xl border bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 transition @error('email') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:ring-brand-500/30 focus:border-brand-500 @enderror"
                placeholder="you@example.com">
            <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-sm" />
        </div>

        <div>
            <label for="store_name" class="block text-sm font-medium text-stone-700 mb-1.5">Café name</label>
            <input id="store_name" type="text" name="store_name" value="{{ old('store_name') }}" required autocomplete="organization"
                @error('store_name') aria-invalid="true" @enderror
                class="w-full rounded-xl border bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 transition @error('store_name') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:ring-brand-500/30 focus:border-brand-500 @enderror"
                placeholder="Your café name">
            <x-input-error :messages="$errors->get('store_name')" class="mt-1.5 text-sm" />
        </div>

        <div x-data="{ visible: false }">
            <label for="password" class="block text-sm font-medium text-stone-700 mb-1.5">Password</label>
            <div class="relative">
                <input id="password" type="password" :type="visible ? 'text' : 'password'" name="password" required minlength="8" autocomplete="new-password" aria-describedby="password-help"
                    @error('password') aria-invalid="true" @enderror
                    class="w-full rounded-xl border bg-stone-50 px-4 py-3 pr-20 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 transition @error('password') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:ring-brand-500/30 focus:border-brand-500 @enderror">
                <button type="button" @click="visible = !visible" :aria-pressed="visible.toString()" aria-controls="password" :aria-label="visible ? 'Hide password' : 'Show password'" x-text="visible ? 'Hide' : 'Show'" class="absolute inset-y-0 right-1 flex min-h-11 min-w-14 items-center justify-center rounded-lg px-2 text-sm font-semibold text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">Show</button>
            </div>
            <p id="password-help" class="mt-1.5 text-xs text-stone-600">Use at least 8 characters.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-sm" />
        </div>

        <div x-data="{ visible: false }">
            <label for="password_confirmation" class="block text-sm font-medium text-stone-700 mb-1.5">Confirm password</label>
            <div class="relative">
                <input id="password_confirmation" type="password" :type="visible ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password"
                    @error('password_confirmation') aria-invalid="true" @enderror
                    class="w-full rounded-xl border bg-stone-50 px-4 py-3 pr-20 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 transition @error('password_confirmation') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:ring-brand-500/30 focus:border-brand-500 @enderror">
                <button type="button" @click="visible = !visible" :aria-pressed="visible.toString()" aria-controls="password_confirmation" :aria-label="visible ? 'Hide password confirmation' : 'Show password confirmation'" x-text="visible ? 'Hide' : 'Show'" class="absolute inset-y-0 right-1 flex min-h-11 min-w-14 items-center justify-center rounded-lg px-2 text-sm font-semibold text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">Show</button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5 text-sm" />
        </div>

        @if(config('merchant_signup.turnstile_enabled'))
            <div class="space-y-2">
                <div id="signup-security-check" class="cf-turnstile" data-sitekey="{{ config('merchant_signup.site_key') }}"
                    data-action="{{ config('merchant_signup.action') }}" data-theme="light" data-size="flexible"></div>
                <p class="text-xs leading-5 text-stone-500">Protected from spam by Cloudflare Turnstile.
                    <a class="underline underline-offset-2" href="https://www.cloudflare.com/privacypolicy/" target="_blank" rel="noopener noreferrer">Privacy</a>
                </p>
                <noscript><p class="text-sm text-red-700">Please enable JavaScript to complete the signup security check.</p></noscript>
            </div>
            <script>
                const signupSecurityCheck = document.getElementById('signup-security-check');
                if (signupSecurityCheck.clientWidth < 300) signupSecurityCheck.dataset.size = 'compact';
            </script>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endif
        <x-input-error :messages="$errors->get('signup_verification')" class="mt-1.5 text-sm" />

        <x-ui.button type="submit" variant="primary" class="w-full" data-loading-text="Creating account...">
            Create my free café account
        </x-ui.button>
        <p class="-mt-2 text-center text-sm text-stone-600">No credit card required. Takes about two minutes.</p>
    </form>

    <script>
        (() => {
            const form = document.getElementById('register-form');
            const fields = ['name', 'email', 'store_name'];
            const storageKey = 'kawhe_merchant_signup_draft';
            try {
                const saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
                if (Date.now() - saved.savedAt < 30 * 60 * 1000) {
                    fields.forEach((name) => {
                        const input = form.elements.namedItem(name);
                        if (input instanceof HTMLInputElement && !input.value && typeof saved[name] === 'string') input.value = saved[name];
                    });
                } else {
                    sessionStorage.removeItem(storageKey);
                }
            } catch (_) {}

            const saveDraft = () => {
                try {
                    const draft = { savedAt: Date.now() };
                    fields.forEach((name) => { draft[name] = form.elements.namedItem(name)?.value || ''; });
                    sessionStorage.setItem(storageKey, JSON.stringify(draft));
                } catch (_) {}
            };
            form.addEventListener('input', saveDraft);
            form.addEventListener('submit', saveDraft);
        })();
    </script>

    <p class="mt-6 text-center text-sm text-stone-500">
        Already have an account?
        <a href="{{ route('login') }}" class="text-brand-600 hover:text-brand-700 font-medium">Sign in</a>
    </p>
</x-guest-layout>
