<x-onboarding-layout>
    <x-slot name="header">Your card is ready</x-slot>

    <x-onboarding-step-layout
        :step="4"
        :store="$store"
        title="Download your join poster"
        subtitle="Preview the A4 poster your customers will scan, then download it for your counter."
        :backUrl="route('merchant.onboarding.wizard.customer-form')"
    >
        @if($errors->has('poster'))
            <x-ui.alert variant="danger" class="mb-6">{{ $errors->first('poster') }}</x-ui.alert>
        @endif
        <div class="grid items-start gap-6 lg:grid-cols-2">
            <section class="min-w-0 rounded-2xl border border-stone-200 bg-white p-5 sm:p-6" aria-labelledby="ready-preview-title">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h2 id="ready-preview-title" class="text-lg font-semibold">Your join poster preview</h2>
                    <a href="{{ route('merchant.stores.qr.pdf', ['store' => $store, 'preview' => 1]) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-700 underline">Open full size ↗</a>
                </div>
                <div id="join-poster-preview" class="relative mx-auto w-full overflow-hidden rounded-lg border border-stone-200 shadow-sm" style="aspect-ratio: 794 / 1123">
                    <iframe src="{{ route('merchant.stores.qr.pdf', ['store' => $store, 'preview' => 1]) }}" title="A4 join poster preview for {{ $store->name }}" class="pointer-events-none absolute left-0 top-0 border-0" style="width: 794px; height: 1123px; transform-origin: top left"></iframe>
                </div>
                <p class="mt-4 text-sm text-stone-500">This preview uses the same poster design and join QR code as the PDF.</p>
            </section>

            <div class="min-w-0 space-y-6">
                <section class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6" aria-labelledby="share-title">
                    <h2 id="share-title" class="text-lg font-semibold">Download and share</h2>
                    <p class="mt-2 text-sm text-stone-600">Print the PDF for your counter. Customers scan its QR code to join and get their own card.</p>
                    @if($store->launch_poster_downloaded_at)
                        <p class="mt-3 text-sm font-semibold text-brand-700">✓ Poster downloaded. You can continue.</p>
                    @endif
                    <label for="join-link" class="mb-2 block text-sm font-medium">Customer signup link</label>
                    <div class="flex min-w-0 gap-2">
                        <input type="text" id="join-link" value="{{ $joinUrl }}" readonly class="min-w-0 flex-1 rounded-xl border border-stone-300 bg-stone-50 px-3 py-3 text-sm">
                        <x-ui.button type="button" onclick="copyJoinLink()" variant="secondary">Copy</x-ui.button>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <x-ui.button href="{{ $joinUrl }}" target="_blank" rel="noopener" variant="secondary" class="min-h-11 w-full sm:col-span-2">Open customer signup</x-ui.button>
                        <a href="{{ route('merchant.stores.qr.pdf', $store) }}" id="onboarding-poster-download" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-stone-300 px-3 py-3 text-center text-sm font-semibold text-brand-700">Download poster (PDF)</a>
                        <a href="{{ route('merchant.stores.qr.image', $store) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-stone-300 px-3 py-3 text-center text-sm font-semibold text-brand-700">Download QR (SVG)</a>
                    </div>
                </section>
                <section class="rounded-2xl bg-brand-50 p-5 sm:p-6">
                    <h2 class="font-semibold text-brand-900">Try it before sharing</h2>
                    <p class="mt-2 text-sm leading-relaxed text-brand-900">Scan the join QR on your phone, sign up and save your card to Wallet. Then use the merchant scanner to test a stamp.</p>
                    <p class="mt-3 text-sm text-brand-900">Once your poster is downloaded, continue to the merchant app. You can change the design later.</p>
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
                    <x-ui.button type="submit" variant="primary" size="lg" class="w-full sm:w-auto rounded-xl min-w-[160px]">Continue to app install</x-ui.button>
                </form>
            </div>
        </x-slot>
    </x-onboarding-step-layout>

    <script>
        const posterPreview = document.getElementById('join-poster-preview');
        if (posterPreview && 'ResizeObserver' in window) {
            const posterFrame = posterPreview.querySelector('iframe');
            const resizePoster = () => {
                posterFrame.style.transform = `scale(${posterPreview.clientWidth / 794})`;
            };
            new ResizeObserver(resizePoster).observe(posterPreview);
            resizePoster();
        }

        document.getElementById('onboarding-poster-download')?.addEventListener('click', async function (event) {
            event.preventDefault();
            try {
                const response = await fetch(this.href, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('Poster download failed');
                const blob = await response.blob();
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'join-poster.pdf';
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(() => { URL.revokeObjectURL(link.href); window.location.reload(); }, 500);
            } catch (error) {
                window.location.href = this.href;
            }
        });

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
