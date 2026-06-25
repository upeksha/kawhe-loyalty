<x-onboarding-layout>
    <x-slot name="header">Your card is ready</x-slot>

    <x-onboarding-step-layout
        :step="4"
        :totalSteps="4"
        title="Your loyalty card is ready"
        subtitle="You can start collecting customers today. Review the checklist below first, then finish setup and start sharing your card."
        :backUrl="route('merchant.onboarding.wizard.customer-form')"
    >
        <div class="space-y-8">
            <x-ui.alert variant="success">
                <p class="font-semibold">Your default loyalty card is ready.</p>
                <p class="mt-0.5">You can start sharing your QR code right away.</p>
            </x-ui.alert>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- QR + actions --}}
                <div class="lg:col-span-2 space-y-6">
                    <x-ui.section-panel class="rounded-[24px] border-stone-200/80 p-6 sm:p-8">
                        <p class="text-sm font-semibold text-stone-600 mb-4">Share your QR code or link</p>
                        <div class="flex flex-col sm:flex-row items-center gap-6">
                            <div class="flex-shrink-0 p-4 bg-white rounded-xl border border-stone-200 inline-block shadow-sm">
                                {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($joinUrl) !!}
                            </div>
                            <div class="flex-1 w-full min-w-0">
                                <label for="join-link" class="block text-sm font-medium text-stone-700 mb-1.5">Join link</label>
                                <div class="flex gap-2">
                                    <input type="text" id="join-link" value="{{ $joinUrl }}" readonly class="block w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-2.5 text-sm text-stone-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500" />
                                    <x-ui.button type="button" onclick="copyJoinLink()" variant="primary" size="md" class="flex-shrink-0">Copy</x-ui.button>
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
                    </x-ui.section-panel>

                    <div class="flex flex-wrap gap-3">
                        <form method="GET" action="{{ route('merchant.stores.qr.pdf', $store) }}">
                            <x-ui.button type="submit" variant="secondary" size="md">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Download poster (PDF)
                            </x-ui.button>
                        </form>
                        <form method="GET" action="{{ route('merchant.stores.qr.image', $store) }}">
                            <x-ui.button type="submit" variant="secondary" size="md">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2m4 0h-2m-4 4h6m-6-2h2m2 0h2m-4-4h2m2 0h2"/></svg>
                                Download QR (SVG)
                            </x-ui.button>
                        </form>
                        <x-ui.button href="{{ $joinUrl }}" target="_blank" rel="noopener" variant="secondary" size="md">
                            Open test join page
                        </x-ui.button>
                    </div>
                </div>

                {{-- How it works + phone preview --}}
                <div class="lg:col-span-1">
                    <div class="sticky top-8 space-y-4">
                        <div class="rounded-xl border border-stone-200 bg-stone-900 p-4 shadow-lg">
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-400 mb-3 text-center">Customer preview</p>
                            <div class="mx-auto w-[220px] rounded-[2rem] border-[10px] border-stone-700 bg-stone-800 p-2 shadow-2xl">
                                <div class="rounded-[1.25rem] overflow-hidden bg-white aspect-[9/16]">
                                    <iframe
                                        src="{{ $joinUrl }}"
                                        title="Join page preview"
                                        class="w-[200%] h-[200%] origin-top-left scale-50 border-0 pointer-events-none"
                                        loading="lazy"
                                        tabindex="-1"
                                    ></iframe>
                                </div>
                            </div>
                            <p class="mt-3 text-center text-xs leading-relaxed text-stone-400">
                                This is what customers see after scanning your QR code.
                            </p>
                            <a href="{{ $joinUrl }}" target="_blank" rel="noopener" class="mt-3 block text-center text-sm font-medium text-brand-400 hover:text-brand-300 transition-colors">
                                Open full preview →
                            </a>
                        </div>
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
</x-onboarding-layout>
