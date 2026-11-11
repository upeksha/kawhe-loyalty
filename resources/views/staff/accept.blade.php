<x-guest-layout>
    <h1 class="mb-1 text-xl font-bold text-stone-900">Join {{ $invitation->store->name }}</h1>
    <p class="mb-5 text-sm text-stone-500">Staff invitation for {{ $invitation->email }}</p>

    <p class="mb-6 rounded-xl bg-stone-50 px-4 py-3 text-sm leading-6 text-stone-700">
        You can {{ $invitation->can_stamp ? 'stamp cards' : '' }}{{ $invitation->can_stamp && $invitation->can_redeem ? ' and ' : '' }}{{ $invitation->can_redeem ? 'redeem rewards' : '' }} at this café.
    </p>

    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if (auth()->check() && (strtolower(auth()->user()->email) !== $invitation->email || !auth()->user()->hasVerifiedEmail()))
        <p class="mb-5 text-sm leading-6 text-stone-600">Sign in with the invited email address and verify it before accepting. Your current account has not been changed.</p>
        @if (strtolower(auth()->user()->email) === $invitation->email)
            <a href="{{ route('verification.notice') }}" class="mb-3 inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-stone-300 px-4 text-sm font-semibold text-brand-700 hover:bg-stone-50">Verify email</a>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button type="submit" class="w-full">Log out</x-ui.button>
        </form>
    @elseif (!auth()->check() && $existing)
        <p class="mb-5 text-sm leading-6 text-stone-600">You already have a Kawhe account. Sign in to accept this invitation.</p>
        <a href="{{ route('login') }}" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-brand-700 px-4 text-sm font-semibold text-white hover:bg-brand-800">Sign in</a>
    @else
        <form method="POST" action="{{ route('staff.accept', ['token' => $token]) }}" class="space-y-5">
            @csrf
            @guest
                <p class="text-sm leading-6 text-stone-600">Create your staff login. No café setup or payment is needed.</p>

                <div>
                    <label for="name" class="mb-1.5 block text-sm font-medium text-stone-700">Full name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus maxlength="255" autocomplete="name"
                        @error('name') aria-invalid="true" @enderror
                        class="w-full rounded-xl border bg-stone-50 px-4 py-3 text-sm text-stone-900 focus:outline-none focus:ring-2 @error('name') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:border-brand-500 focus:ring-brand-500/30 @enderror">
                    <x-input-error :messages="$errors->get('name')" class="mt-1.5 text-sm" />
                </div>

                <div x-data="{ visible: false }">
                    <label for="password" class="mb-1.5 block text-sm font-medium text-stone-700">Password</label>
                    <div class="relative">
                        <input id="password" type="password" :type="visible ? 'text' : 'password'" name="password" required minlength="8" autocomplete="new-password" aria-describedby="staff-password-help"
                            @error('password') aria-invalid="true" @enderror
                            class="w-full rounded-xl border bg-stone-50 px-4 py-3 pr-20 text-sm text-stone-900 focus:outline-none focus:ring-2 @error('password') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:border-brand-500 focus:ring-brand-500/30 @enderror">
                        <button type="button" @click="visible = !visible" :aria-pressed="visible.toString()" aria-controls="password" :aria-label="visible ? 'Hide password' : 'Show password'" x-text="visible ? 'Hide' : 'Show'" class="absolute inset-y-0 right-1 flex min-h-11 min-w-14 items-center justify-center rounded-lg px-2 text-sm font-semibold text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">Show</button>
                    </div>
                    <p id="staff-password-help" class="mt-1.5 text-xs text-stone-600">Use at least 8 characters.</p>
                    <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-sm" />
                </div>

                <div x-data="{ visible: false }">
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-stone-700">Confirm password</label>
                    <div class="relative">
                        <input id="password_confirmation" type="password" :type="visible ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password"
                            @error('password_confirmation') aria-invalid="true" @enderror
                            class="w-full rounded-xl border bg-stone-50 px-4 py-3 pr-20 text-sm text-stone-900 focus:outline-none focus:ring-2 @error('password_confirmation') border-red-300 focus:border-red-500 focus:ring-red-500/30 @else border-stone-300 focus:border-brand-500 focus:ring-brand-500/30 @enderror">
                        <button type="button" @click="visible = !visible" :aria-pressed="visible.toString()" aria-controls="password_confirmation" :aria-label="visible ? 'Hide password confirmation' : 'Show password confirmation'" x-text="visible ? 'Hide' : 'Show'" class="absolute inset-y-0 right-1 flex min-h-11 min-w-14 items-center justify-center rounded-lg px-2 text-sm font-semibold text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">Show</button>
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5 text-sm" />
                </div>
            @else
                <p class="text-sm leading-6 text-stone-600">You are signed in and ready to join this café’s team.</p>
            @endguest

            <x-ui.button type="submit" class="w-full">Accept invitation</x-ui.button>
        </form>
    @endif
</x-guest-layout>
