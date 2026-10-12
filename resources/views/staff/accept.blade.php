<x-guest-layout>
    <h1 class="text-xl font-bold mb-3">Join {{ $invitation->store->name }}</h1>
    <p class="text-sm text-stone-600 mb-3">Invitation for {{ $invitation->email }}. You can {{ $invitation->can_stamp ? 'stamp cards' : '' }}{{ $invitation->can_stamp && $invitation->can_redeem ? ' and ' : '' }}{{ $invitation->can_redeem ? 'redeem rewards' : '' }} at this store.</p>
    @if($errors->any())<div role="alert" class="mb-4 text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if(auth()->check() && (strtolower(auth()->user()->email) !== $invitation->email || !auth()->user()->hasVerifiedEmail()))
        <p class="text-sm mb-4">Sign in with the invited email address and verify it before accepting. Your current account has not been changed.</p>
        @if(strtolower(auth()->user()->email) === $invitation->email)<a class="underline" href="{{ route('verification.notice') }}">Verify email</a>@endif
        <form method="POST" action="{{ route('logout') }}">@csrf<x-primary-button>Log out</x-primary-button></form>
    @elseif(!auth()->check() && $existing)
        <p class="mb-4 text-sm">You already have a Kawhe account. Sign in to accept this invitation.</p>
        <a class="underline" href="{{ route('login') }}">Sign in</a>
    @else
        <form method="POST" action="{{ route('staff.accept', ['token'=>$token]) }}" class="space-y-4">
            @csrf
            @guest
            <p class="text-sm">Create your own staff login. No store setup or payment is needed.</p>
            <label class="block text-sm">Full name<input type="text" name="name" required maxlength="255" value="{{ old('name') }}" autocomplete="name" class="mt-1 block w-full rounded-lg border-stone-300"></label>
            <label class="block text-sm">Password<input type="password" name="password" required autocomplete="new-password" class="mt-1 block w-full rounded-lg border-stone-300"></label>
            <label class="block text-sm">Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 block w-full rounded-lg border-stone-300"></label>
            @endguest
            <x-primary-button>Accept invitation</x-primary-button>
        </form>
    @endif
</x-guest-layout>
