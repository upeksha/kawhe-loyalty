<x-onboarding-layout>
    <x-slot name="header">Your card is ready</x-slot>

    <x-onboarding-step-layout
        :step="4"
        :totalSteps="4"
        title="Your loyalty card is ready"
        subtitle="Check your card, try the signup link, then finish setup."
        :backUrl="route('merchant.onboarding.wizard.customer-form')"
    >
        <div class="grid items-start gap-6 lg:grid-cols-2">
            <section class="min-w-0 rounded-2xl border border-stone-200 bg-white p-5 sm:p-6" aria-labelledby="ready-preview-title">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h2 id="ready-preview-title" class="text-lg font-semibold">Your customer card</h2>
                    <a href="{{ route('merchant.onboarding.wizard.card-design') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-700 underline">Edit design</a>
                </div>
                @if($program && \App\Support\CoffeeCardSample::programEnabled($program))
                    @php
                        [$background, $ink] = \App\Support\CardDesign::colors($program);
                        $target = max(1, (int) $program->reward_target);
                        $artwork = app(\App\Services\Wallet\Artwork\CardStyleArtwork::class)->render($target, 0, false, 'web', \App\Support\CardDesign::options($program));
                    @endphp
                    <div class="mx-auto max-w-sm overflow-hidden rounded-2xl" style="background-color: {{ $background }}; color: {{ $ink }}">
                        <div class="space-y-2 p-5">
                            @if($program->logo_url)<img src="{{ $program->logo_url }}" alt="{{ $store->name }} logo" class="max-h-14 max-w-36 object-contain">@endif
                            <p class="break-words text-lg font-semibold">{{ $store->name }}</p>
                            <p class="text-sm">0 / {{ $target }} stamps · 0 rewards</p>
                        </div>
                        <img src="data:image/png;base64,{{ base64_encode($artwork) }}" width="720" height="470" alt="Saved card design with empty stamps" class="block h-auto w-full">
                        <div class="space-y-3 p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide">Your reward</p>
                            <p class="break-words text-lg font-semibold">{{ $program->reward_title }}</p>
                            <div class="rounded-xl bg-white p-4 text-center text-stone-600">
                                <img src="{{ asset('images/loyalty-card-demo-qr.svg') }}" width="100" height="100" alt="Non-scannable sample card QR" class="mx-auto h-24 w-24">
                                <p class="mt-2 text-xs">Sample card · not for scanning</p>
                            </div>
                        </div>
                    </div>
                @elseif($program)
                    <div class="flex justify-center"><x-loyalty-design-preview :store="$store" :program="$program" /></div>
                @endif
                <p class="mt-4 text-sm text-stone-500">Preview only—no customer account is created. Apple and Google Wallet use the same design in their own layouts.</p>
            </section>

            <div class="min-w-0 space-y-6">
                <section class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6" aria-labelledby="share-title">
                    <h2 id="share-title" class="text-lg font-semibold">Invite your first customer</h2>
                    <p class="mt-2 text-sm text-stone-600">Customers scan this QR code to join and get their own card.</p>
                    <div class="mx-auto my-5 flex w-fit max-w-full justify-center rounded-xl border border-stone-200 bg-white p-4 [&_svg]:h-auto [&_svg]:max-w-full">
                        {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(184)->margin(1)->generate($joinUrl) !!}
                    </div>
                    <label for="join-link" class="mb-2 block text-sm font-medium">Customer signup link</label>
                    <div class="flex min-w-0 gap-2">
                        <input type="text" id="join-link" value="{{ $joinUrl }}" readonly class="min-w-0 flex-1 rounded-xl border border-stone-300 bg-stone-50 px-3 py-3 text-sm">
                        <x-ui.button type="button" onclick="copyJoinLink()" variant="secondary">Copy</x-ui.button>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <x-ui.button href="{{ $joinUrl }}" target="_blank" rel="noopener" variant="secondary" class="min-h-11 w-full sm:col-span-2">Open customer signup</x-ui.button>
                        <a href="{{ route('merchant.stores.qr.pdf', $store) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-stone-300 px-3 py-3 text-center text-sm font-semibold text-brand-700">Download poster (PDF)</a>
                        <a href="{{ route('merchant.stores.qr.image', $store) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-stone-300 px-3 py-3 text-center text-sm font-semibold text-brand-700">Download QR (SVG)</a>
                    </div>
                </section>
                <section class="rounded-2xl bg-brand-50 p-5 sm:p-6">
                    <h2 class="font-semibold text-brand-900">Try it before sharing</h2>
                    <p class="mt-2 text-sm leading-relaxed text-brand-900">Scan the join QR on your phone, sign up and save your card to Wallet. Then use the merchant scanner to test a stamp.</p>
                    <p class="mt-3 text-sm text-brand-900">Happy with your card? Finish setup below. You can change your design later.</p>
                </section>
            </div>
        </div>

        <x-slot name="actions">
            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                <form method="GET" action="{{ route('merchant.onboarding.wizard.customer-form') }}" class="w-full sm:w-auto">
                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 text-sm font-medium text-stone-600 hover:text-stone-900 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 rounded-lg py-2 px-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Back
                    </button>
                </form>
                <form method="POST" action="{{ route('merchant.onboarding.wizard.card-ready.advance') }}" class="w-full sm:w-auto">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="lg" class="w-full sm:w-auto rounded-xl min-w-[160px]">Finish setup</x-ui.button>
                </form>
            </div>
        </x-slot>
    </x-onboarding-step-layout>

    <script>
        function showCopyToast(message, isError = false) {
            const existing = document.getElementById('copy-toast');
            if (existing) {
                existing.remove();
            }

            const toast = document.createElement('div');
            toast.id = 'copy-toast';
            toast.className = `fixed bottom-6 right-6 z-50 px-4 py-3 rounded-lg text-sm font-medium shadow-lg transition-opacity duration-300 ${isError ? 'bg-red-600 text-white' : 'bg-stone-900 text-white'}`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 1800);
        }

        function copyJoinLink() {
            const el = document.getElementById('join-link');
            if (!el) return;

            const value = el.value;
            if (!value) {
                showCopyToast('No link to copy yet.', true);
                return;
            }

            Promise.resolve().then(() => navigator.clipboard.writeText(value)).then(function() {
                showCopyToast('Join link copied.');
            }).catch(function() {
                el.select();
                el.setSelectionRange(0, 99999);
                try {
                    const copied = document.execCommand('copy');
                    showCopyToast(copied ? 'Join link copied.' : 'Could not copy link.', !copied);
                } catch (e) {
                    showCopyToast('Could not copy link.', true);
                }
            });
        }
    </script>
</x-onboarding-layout>
