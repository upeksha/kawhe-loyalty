<x-merchant-layout>
    <x-slot name="header">Your card is ready</x-slot>

    <x-onboarding-step-layout
        :step="4"
        :totalSteps="5"
        title="Your loyalty card is ready"
        subtitle="You can start collecting customers today. Review the checklist below first so your first launch feels polished and easy to use."
        :backUrl="route('merchant.onboarding.wizard.customer-form')"
    >
        <div class="space-y-8">
            {{-- Success message + 50 free --}}
            <div class="flex items-start gap-4 p-5 rounded-xl border border-green-200 bg-green-50/80">
                <div class="flex-shrink-0 w-11 h-11 rounded-full bg-green-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div>
                    <p class="font-semibold text-green-900">Your first 50 customer cards are free.</p>
                    <p class="text-sm text-green-800 mt-0.5">No setup delay. Your QR is ready to use.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- QR + actions --}}
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-2xl border border-stone-200 p-6 sm:p-8 shadow-lg shadow-stone-200/30">
                        <p class="text-sm font-semibold text-stone-600 mb-4">Share your QR code or link</p>
                        <div class="flex flex-col sm:flex-row items-center gap-6">
                            <div class="flex-shrink-0 p-4 bg-white rounded-xl border border-stone-200 inline-block shadow-sm">
                                {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($joinUrl) !!}
                            </div>
                            <div class="flex-1 w-full min-w-0">
                                <label for="join-link" class="block text-sm font-medium text-stone-700 mb-1.5">Join link</label>
                                <div class="flex gap-2">
                                    <input type="text" id="join-link" value="{{ $joinUrl }}" readonly class="block w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-2.5 text-sm text-stone-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500" />
                                    <button type="button" onclick="copyJoinLink()" class="inline-flex items-center justify-center font-medium rounded-xl px-4 py-2.5 text-sm bg-brand-600 text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 flex-shrink-0 transition-colors">Copy</button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 rounded-xl border border-stone-200 bg-stone-50/70 p-4">
                            <p class="text-sm font-semibold text-stone-800">What to check before launch</p>
                            <ul class="mt-2 space-y-2 text-sm leading-relaxed text-stone-600">
                                <li>The join page looks clear on a phone, not just on a desktop screen.</li>
                                <li>The reward wording is short enough to understand in one quick glance.</li>
                                <li>The poster QR scans comfortably from counter distance.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('merchant.stores.qr.pdf', $store) }}" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-xl bg-stone-100 text-stone-800 hover:bg-stone-200 border border-stone-200 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stone-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Download poster (PDF)
                        </a>
                        <a href="{{ $joinUrl }}" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-xl bg-stone-100 text-stone-800 hover:bg-stone-200 border border-stone-200 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stone-500">
                            Open test join page
                        </a>
                    </div>
                </div>

                {{-- How it works --}}
                <div class="lg:col-span-1">
                    <div class="sticky top-8 space-y-4">
                        <div class="rounded-xl border border-stone-200 bg-stone-50/70 p-5 shadow-sm">
                            <p class="text-sm font-semibold text-stone-800 mb-4">Before you share</p>
                            <ul class="space-y-3 text-sm text-stone-700">
                                <li class="rounded-lg border border-stone-200 bg-white px-4 py-3">
                                    <span class="font-medium text-stone-800">Test the join page once yourself</span>
                                    <p class="mt-1 text-xs leading-relaxed text-stone-500">Make sure the store name, reward wording, and signup fields feel clear on mobile.</p>
                                </li>
                                <li class="rounded-lg border border-stone-200 bg-white px-4 py-3">
                                    <span class="font-medium text-stone-800">Print or save the poster</span>
                                    <p class="mt-1 text-xs leading-relaxed text-stone-500">Use the PDF so staff can place it near the counter. If the poster feels crowded, update the reward wording or branding before you print.</p>
                                </li>
                                <li class="rounded-lg border border-stone-200 bg-white px-4 py-3">
                                    <span class="font-medium text-stone-800">Check wallet branding later if needed</span>
                                    <p class="mt-1 text-xs leading-relaxed text-stone-500">If you skipped wallet-specific images, Kawhe will still generate a safe branded default presentation.</p>
                                </li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-stone-200 bg-stone-50/70 p-5 shadow-sm">
                        <p class="text-sm font-semibold text-stone-800 mb-4">How it works</p>
                        <ol class="space-y-4 text-sm text-stone-700">
                            <li class="flex gap-3">
                                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-brand-100 text-brand-700 font-semibold flex items-center justify-center text-xs">1</span>
                                <span>Customer scans your QR code</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-brand-100 text-brand-700 font-semibold flex items-center justify-center text-xs">2</span>
                                <span>They save their loyalty card</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-brand-100 text-brand-700 font-semibold flex items-center justify-center text-xs">3</span>
                                <span>You stamp and reward returning customers</span>
                            </li>
                        </ol>
                        </div>
                        <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-semibold text-stone-800">Poster guidance</p>
                            <p class="mt-2 text-sm leading-relaxed text-stone-600">
                                Your PDF poster uses the same reward title, store branding, and join link customers will see live. If anything feels unclear here, it will usually feel unclear on the printed poster too.
                            </p>
                        </div>
                    </div>
                </div>
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
                    <x-ui.button type="submit" variant="primary" size="lg" class="w-full sm:w-auto rounded-xl min-w-[160px]">Continue trial</x-ui.button>
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

            navigator.clipboard.writeText(value).then(function() {
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
</x-merchant-layout>
