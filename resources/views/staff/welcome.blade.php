<x-guest-layout>
    @if (session('status'))
        <p role="status" class="mb-5 rounded-xl bg-brand-50 px-4 py-3 text-sm font-medium text-brand-800">{{ session('status') }}</p>
    @endif

    @if ($storeNames->isEmpty())
        <h1 class="mb-2 text-xl font-bold text-stone-900">No active café access</h1>
        <p class="text-sm leading-6 text-stone-600">Ask the café owner to invite you again before signing in to Kawhe Merchant.</p>
    @else
        <h1 class="mb-2 text-xl font-bold text-stone-900">You’re ready to scan</h1>
        <p class="text-sm leading-6 text-stone-600">Your staff login is ready for {{ $storeNames->join(', ', ' and ') }}. Use the free Kawhe Merchant app on the phone you’ll use at the counter.</p>

        <div class="mt-6 rounded-xl bg-stone-50 p-4">
            <p class="text-sm font-semibold text-stone-900">Get Kawhe Merchant</p>
            <div class="mt-3 grid gap-2">
                <a href="{{ config('services.merchant_app.apple_store_url') }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-brand-700 px-3 text-center text-sm font-semibold text-white hover:bg-brand-800">iPhone · App Store</a>
                <a href="{{ config('services.merchant_app.google_play_url') }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-brand-700 px-3 text-center text-sm font-semibold text-brand-700 hover:bg-white">Android · Google Play</a>
            </div>
        </div>

        <ol class="mt-6 space-y-4 text-sm leading-6 text-stone-700">
            <li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-50 font-semibold text-brand-800">1</span><span>Install Kawhe Merchant on your phone and open it.</span></li>
            <li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-50 font-semibold text-brand-800">2</span><span>Sign in with <strong class="break-all text-stone-900">{{ auth()->user()->email }}</strong> and the same password you used for this Kawhe account.</span></li>
            <li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-50 font-semibold text-brand-800">3</span><span>Select your café, allow camera access, then scan a customer’s card to add a stamp or redeem a reward if your permissions allow it.</span></li>
        </ol>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-7 border-t border-stone-200 pt-5 text-center">
        @csrf
        <button type="submit" class="min-h-11 text-sm font-medium text-brand-700 underline underline-offset-4 hover:text-brand-800">Sign out of the website</button>
    </form>
</x-guest-layout>
