@php
    $launch = app(\App\Support\MerchantLaunchChecklist::class)->forUser(auth()->user(), $store);
    $appInstalled = collect($launch['steps'])->firstWhere('key', 'app')['done'];
@endphp

<x-onboarding-layout>
    <x-slot name="header">{{ $launch['ready'] ? 'Your café is ready' : ($appInstalled ? 'Try a test stamp' : 'Install Kawhe Merchant') }}</x-slot>

    <x-onboarding-step-layout
        :step="$appInstalled ? 6 : 5"
        :store="$store"
        :title="$launch['ready'] ? 'Your loyalty programme is live' : ($appInstalled ? 'Complete a test stamp' : 'Install Kawhe Merchant')"
        :subtitle="$launch['ready'] ? 'Display your QR poster and invite your first customer.' : ($appInstalled ? 'One quick scan checks that your card and the merchant app work together.' : $store->name . ' · Get the free app your team will use at the counter.')"
    >
        <x-slot:preview>
            @if($launch['ready'])
                <div class="space-y-4 text-center">
                    <h2 class="text-2xl font-bold">Your join poster</h2>
                    <div class="relative mx-auto w-full max-w-[18rem] overflow-hidden rounded-lg border border-stone-200 shadow-sm" style="aspect-ratio: 794 / 1123" x-data
                        x-init="const container = $el; const pdf = $refs.poster; new ResizeObserver(() => pdf.style.transform = 'scale(' + container.clientWidth / 794 + ')').observe(container)">
                        <iframe x-ref="poster" src="{{ route('merchant.stores.qr.pdf', ['store' => $store, 'preview' => 1]) }}" title="A4 join poster preview for {{ $store->name }}" class="pointer-events-none absolute left-0 top-0 border-0" style="width: 794px; height: 1123px; transform-origin: top left"></iframe>
                    </div>
                    <x-ui.button :href="route('merchant.stores.qr.pdf', ['store' => $store, 'preview' => 1])" variant="secondary" target="_blank" rel="noopener">Open full size ↗</x-ui.button>
                </div>
            @elseif(!$appInstalled)
                <div class="space-y-4 text-center">
                    <h2 class="text-2xl font-bold">For your team at the counter</h2>
                    <x-onboarding-phone>
                        <div class="space-y-5 rounded-xl bg-white p-4">
                            <x-application-logo class="mx-auto h-9 w-auto" />
                            <p class="font-semibold">{{ $store->name }}</p>
                            <div class="mx-auto grid aspect-square w-full max-w-48 place-items-center rounded-xl bg-brand-900">
                                <img src="{{ asset('images/onboarding/scan-guides.svg') }}" alt="" width="86" height="86">
                            </div>
                            <p class="text-sm font-semibold">Scan a customer card</p>
                        </div>
                    </x-onboarding-phone>
                    <p class="text-sm">Customers can show their card from their browser or Wallet.</p>
                </div>
            @else
                <div class="space-y-4 text-center">
                    <h2 class="text-2xl font-bold">Use your computer and phone</h2>
                    <x-onboarding-saved-card :store="$store" />
                    <p class="text-sm">Show the QR from your test customer’s card to Kawhe Merchant.</p>
                </div>
            @endif
        </x-slot:preview>
        @if($launch['ready'])
            <div class="rounded-2xl bg-brand-50 p-6 text-brand-900">
                <p class="text-lg font-semibold">All six launch steps are complete.</p>
                <p class="mt-2">Your poster is downloaded, the merchant app is ready and your test stamp worked.</p>
                <a href="{{ route('merchant.dashboard') }}" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-brand-700 px-5 text-sm font-semibold text-white hover:bg-brand-800">Go to dashboard</a>
            </div>
        @elseif(!$appInstalled)
            <section id="merchant-app" class="scroll-mt-24" aria-labelledby="merchant-app-title">
                <h3 id="merchant-app-title" class="text-lg font-semibold text-stone-900">The free app for your team</h3>
                <p class="mt-3 text-sm leading-6 text-stone-600">Get Kawhe Merchant on the phone your team will use to scan customer cards. Customers do not need this app.</p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ config('services.merchant_app.apple_store_url') }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-xl border border-stone-300 px-4 text-sm font-semibold text-brand-700 hover:bg-brand-50">iPhone · App Store ↗</a>
                    <a href="{{ config('services.merchant_app.google_play_url') }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-xl border border-stone-300 px-4 text-sm font-semibold text-brand-700 hover:bg-brand-50">Android · Google Play ↗</a>
                </div>
                <p class="mt-6 text-sm leading-6 text-stone-600">Sign in with the same owner account you use on the website and select {{ $store->name }}. Your owner account can stamp and redeem; you can invite other staff later. We cannot detect installation from a browser, so confirm it below when you are ready.</p>
                <form method="POST" action="{{ route('merchant.onboarding.launch.app-installed', $store) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="min-h-11 rounded-xl bg-brand-700 px-5 text-sm font-semibold text-white hover:bg-brand-800">I’ve installed Kawhe Merchant</button>
                </form>
            </section>
        @else
            <section id="test-stamp" class="scroll-mt-24" aria-labelledby="test-stamp-title">
                <h3 id="test-stamp-title" class="text-lg font-semibold text-stone-900">Use your computer and phone</h3>
                <ol class="mt-4 grid gap-3 sm:grid-cols-2">
                    <li class="rounded-xl bg-stone-50 p-4 text-sm leading-6 text-stone-700"><strong class="block text-base text-stone-900">1. On your computer</strong> Open the join page, make a test card and leave its QR code on screen.</li>
                    <li class="rounded-xl bg-stone-50 p-4 text-sm leading-6 text-stone-700"><strong class="block text-base text-stone-900">2. On your phone</strong> Open Kawhe Merchant, scan that card and tap “Add 1 stamp”.</li>
                </ol>
                <p class="mt-4 text-sm leading-6 text-stone-600">The final step ticks when the stamp is saved. You can go to your dashboard now and finish this test later.</p>
                <div class="mt-5 grid grid-cols-3 gap-2">
                    <a href="{{ $joinUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 min-w-0 items-center justify-center rounded-xl border border-stone-300 px-2 text-center text-xs font-semibold text-brand-700 hover:bg-brand-50">Open join page ↗</a>
                    <form method="GET" action="{{ route('merchant.onboarding.launch.show', $store) }}" class="min-w-0">
                        <button type="submit" class="h-full min-h-11 w-full rounded-xl border border-stone-300 px-2 text-xs font-semibold text-brand-700 hover:bg-brand-50">Check progress</button>
                    </form>
                    <a href="{{ route('merchant.dashboard') }}" class="inline-flex min-h-11 min-w-0 items-center justify-center rounded-xl bg-brand-700 px-2 text-center text-xs font-semibold text-white hover:bg-brand-800">Continue to dashboard</a>
                </div>
            </section>
        @endif
    </x-onboarding-step-layout>
</x-onboarding-layout>
