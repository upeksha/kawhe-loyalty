<x-dashboard-layout title="Staff invitation"><div class="merchant-design max-w-[660px] space-y-6">
    <h1 class="text-3xl font-semibold sm:text-4xl">Join {{ $invitation->store->name }}</h1>
    <p class="text-sm text-stone-600 mb-3">Invitation for {{ $invitation->email }}. You can {{ $invitation->can_stamp ? 'stamp cards' : '' }}{{ $invitation->can_stamp && $invitation->can_redeem ? ' and ' : '' }}{{ $invitation->can_redeem ? 'redeem rewards' : '' }} at this store.</p>
    @if($errors->any())<div role="alert" class="mb-4 text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if(auth()->check() && (strtolower(auth()->user()->email) !== $invitation->email || !auth()->user()->hasVerifiedEmail()))
        <p class="text-sm mb-4">Sign in with the invited email address and verify it before accepting. Your current account has not been changed.</p>
        @if(strtolower(auth()->user()->email) === $invitation->email)<a class="inline-flex min-h-12 items-center justify-center rounded-lg border border-stone-200 bg-white px-6 text-sm font-semibold text-brand-700" href="{{ route('verification.notice') }}">Verify email</a>@endif
        <form method="POST" action="{{ route('logout') }}">@csrf<x-ui.button type="submit" class="w-full max-w-[400px]">Log out</x-ui.button></form>
    @elseif(!auth()->check() && $existing)
        <p class="mb-4 text-sm">You already have a Kawhe account. Sign in to accept this invitation.</p>
        <a class="inline-flex min-h-12 items-center justify-center rounded-lg border border-stone-200 bg-white px-6 text-sm font-semibold text-brand-700" href="{{ route('login') }}">Sign in</a>
    @else
        <form method="POST" action="{{ route('staff.accept', ['token'=>$token]) }}" class="space-y-6">
            @csrf
            @guest
            <p class="text-sm">Create your own staff login. No store setup or payment is needed.</p>
            <label class="block rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm font-semibold">Full name<input type="text" name="name" required maxlength="255" value="{{ old('name') }}" autocomplete="name" class="mt-1 block w-full rounded-lg border-stone-300"></label>
            <label class="block rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm font-semibold">Password<input type="password" name="password" required autocomplete="new-password" class="mt-1 block w-full rounded-lg border-stone-300"></label>
            <label class="block rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm font-semibold">Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 block w-full rounded-lg border-stone-300"></label>
            @endguest
            <x-ui.button type="submit" class="w-full max-w-[400px]">Accept invitation</x-ui.button>
        </form>
    @endif
</div></x-dashboard-layout>
