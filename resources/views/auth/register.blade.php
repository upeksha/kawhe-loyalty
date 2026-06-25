<x-guest-layout>
    <h1 class="text-xl font-bold text-stone-900 mb-1">Create your account</h1>
    <p class="text-sm text-stone-500 mb-6">Set up your account and first loyalty card</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-5" id="register-form">
        @csrf
        <x-form-error-summary form-id="register-form" max-items="3" />

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
            <label for="store_name" class="block text-sm font-medium text-stone-700 mb-1.5">Store name</label>
            <input id="store_name" type="text" name="store_name" value="{{ old('store_name') }}" required autocomplete="organization"
                @error('store_name') aria-invalid="true" @enderror
                class="w-full rounded-xl border bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 transition @error('store_name') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:ring-brand-500/30 focus:border-brand-500 @enderror"
                placeholder="Your cafe or store name">
            <x-input-error :messages="$errors->get('store_name')" class="mt-1.5 text-sm" />
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-stone-700 mb-1.5">Address <span class="text-stone-400 font-normal">(optional)</span></label>
            <input id="address" type="text" name="address" value="{{ old('address') }}" autocomplete="street-address"
                @error('address') aria-invalid="true" @enderror
                class="w-full rounded-xl border bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 transition @error('address') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:ring-brand-500/30 focus:border-brand-500 @enderror"
                placeholder="Optional for now">
            <x-input-error :messages="$errors->get('address')" class="mt-1.5 text-sm" />
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-stone-700 mb-1.5">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                @error('password') aria-invalid="true" @enderror
                class="w-full rounded-xl border bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 transition @error('password') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:ring-brand-500/30 focus:border-brand-500 @enderror">
            <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-sm" />
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-stone-700 mb-1.5">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                @error('password_confirmation') aria-invalid="true" @enderror
                class="w-full rounded-xl border bg-stone-50 px-4 py-3 text-sm text-stone-900 placeholder-stone-400 focus:outline-none focus:ring-2 transition @error('password_confirmation') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:ring-brand-500/30 focus:border-brand-500 @enderror">
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5 text-sm" />
        </div>

        <x-ui.button type="submit" variant="primary" class="w-full" data-loading-text="Creating account...">
            Create account
        </x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-stone-500">
        Already have an account?
        <a href="{{ route('login') }}" class="text-brand-600 hover:text-brand-700 font-medium">Sign in</a>
    </p>
</x-guest-layout>
