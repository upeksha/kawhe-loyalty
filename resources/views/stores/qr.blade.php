<x-merchant-design-layout title="QR & poster" :subtitle="$store->name . ' · Help customers discover and join your loyalty card.'">
    <x-slot:actions>
        <x-ui.button href="{{ route('merchant.stores.index') }}" variant="ghost">← Back to stores</x-ui.button>
        <x-ui.button href="{{ route('merchant.stores.edit', $store) }}" variant="secondary">Edit store</x-ui.button>
    </x-slot:actions>
    @php
        $walletReady = !empty($store->reward_title)
            && (int) ($store->reward_target ?? 0) > 0
            && !empty($store->background_color)
            && (!empty($store->logo_path) || !empty($store->pass_logo_path));
        $launchChecks = [
            [
                'label' => 'Reward setup',
                'ready' => !empty($store->reward_title) && (int) ($store->reward_target ?? 0) > 0,
                'hint' => 'Customers need a clear reward title and target before you share the QR.',
            ],
            [
                'label' => 'Join flow branding',
                'ready' => !empty($store->background_color) && !empty($store->brand_color),
                'hint' => 'Check your selected card colours and branding before sharing.',
            ],
            [
                'label' => 'Store logo',
                'ready' => !empty($store->logo_path),
                'hint' => 'Your logo helps customers recognise your café.',
            ],
            [
                'label' => 'Wallet branding',
                'ready' => $walletReady,
                'hint' => 'Check a saved card in Apple Wallet or Google Wallet before sharing.',
            ],
        ];
        $launchScore = collect($launchChecks)->where('ready', true)->count();
        $launchLabel = $launchScore >= count($launchChecks)
            ? 'Branding checks complete'
            : ($launchScore >= 3 ? 'Review your branding' : 'Needs review');
        $launchIcon = $launchScore >= count($launchChecks) ? 'check' : ($launchScore >= 3 ? 'review' : 'alert');
        $launchTone = $launchScore >= count($launchChecks)
            ? 'bg-emerald-100 text-emerald-700'
            : ($launchScore >= 3 ? 'bg-amber-100 text-amber-700' : 'bg-accent-100 text-accent-700');
    @endphp


    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card class="p-5 sm:p-6">
            <div class="flex items-center gap-3">
                <span class="qr-section-icon"><x-merchant-icon name="scan" /></span>
                <div><h2 class="text-lg font-semibold text-stone-900">Print a poster</h2>
                <p class="mt-1 text-sm text-stone-500">Place it at your counter so customers can scan and join.</p></div>
            </div>
            <div class="store-qr-display mt-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-brand-700">Your store join QR</p>
                <h3 class="mt-2 text-xl font-semibold text-stone-900">{{ $store->name }}</h3>
                <div class="store-qr-code" role="img" aria-label="QR code to join {{ $store->name }}">
                    {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(240)->margin(2)->generate($joinUrl) !!}
                </div>
                <p class="text-sm font-medium text-stone-700">Scan to join our loyalty card</p>
                <p class="mt-1 text-xs text-stone-500">Open the poster preview to see the full print design.</p>
            </div>
            <div class="mt-5 flex flex-wrap gap-2">
                <x-ui.button onclick="downloadStorePoster('{{ route('merchant.stores.qr.pdf', $store) }}', '{{ \Illuminate\Support\Str::slug($store->name) }}-join-poster.pdf')">Download A4 poster</x-ui.button>
                <x-ui.button href="{{ route('merchant.stores.qr.pdf', ['store' => $store, 'preview' => 1]) }}" variant="secondary" target="_blank" rel="noopener">Preview poster ↗</x-ui.button>
            </div>
            <p class="mt-3 text-xs leading-relaxed text-stone-500">PDF · A4 portrait · Uses your current store branding.</p>
        </x-ui.card>

        <div class="space-y-6 min-w-0">
            <x-ui.card class="p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-stone-900">Share your join link</h2>
                <p class="mt-2 text-sm leading-relaxed text-stone-500">Add this link to your website, social profile or messages. It opens the same join page as your QR code.</p>
                <div class="mt-5">
                    <label for="join-link" class="block text-sm font-medium text-stone-700">Customer join link</label>
                    <div class="mt-2 flex gap-2">
                        <x-ui.input type="text" id="join-link" value="{{ $joinUrl }}" readonly class="min-w-0 flex-1" aria-describedby="join-link-status" />
                        <x-ui.button onclick="copyToClipboard()" variant="secondary" type="button">Copy link</x-ui.button>
                    </div>
                    <p id="join-link-status" role="status" aria-live="polite" class="mt-2 min-h-5 text-xs text-stone-600"></p>
                </div>
                <x-ui.button href="{{ $joinUrl }}" variant="secondary" target="_blank" rel="noopener">Open join page ↗</x-ui.button>
                <div class="mt-6 border-t border-stone-200 pt-5">
                    <h3 class="text-sm font-semibold text-stone-900">Making your own signage?</h3>
                    <p class="mt-2 text-sm leading-relaxed text-stone-500">Download the QR image for menus, table signs or other printed material.</p>
                    <form class="mt-3" method="GET" data-disable-submit-loading="false" action="{{ route('merchant.stores.qr.image', $store) }}">
                        <x-ui.button type="submit" variant="secondary">Download QR (SVG)</x-ui.button>
                    </form>
                </div>
            </x-ui.card>
            <x-ui.card class="p-5 sm:p-6">
                <h2 class="text-base font-semibold text-stone-900">Before you print or share</h2>
                <ol class="mt-4 space-y-3 text-sm leading-relaxed text-stone-600 list-decimal pl-5">
                    <li>Open your join page and check the logo, colours and default card reward.</li>
                    <li>Join as a test customer and save the card to Wallet if you plan to offer it.</li>
                    <li>Print one poster and scan it with your phone before printing more.</li>
                </ol>
            </x-ui.card>
        </div>
    </div>

    <x-ui.card class="p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="text-lg font-semibold text-stone-900">Store launch checklist</h2>
            <p class="mt-1 text-sm text-stone-500">{{ $launchScore }} of {{ count($launchChecks) }} setup checks ready. Review any gaps before sharing.</p></div>
            <x-ui.status-icon-badge :icon="$launchIcon" :label="$launchLabel" :tone="$launchTone" />
        </div>
        <ul class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($launchChecks as $check)
                <li class="rounded-xl border border-stone-200 p-4">
                    <span class="text-xs font-semibold {{ $check['ready'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $check['ready'] ? 'Ready' : 'Review' }}</span>
                    <h3 class="mt-2 text-sm font-semibold text-stone-900">{{ $check['label'] }}</h3>
                    <p class="mt-2 text-xs leading-relaxed text-stone-500">{{ $check['hint'] }}</p>
                </li>
            @endforeach
        </ul>
        <div class="mt-5 flex flex-wrap gap-2">
            <x-ui.button href="{{ route('merchant.stores.edit', $store) }}" variant="secondary">Review store setup</x-ui.button>
            <x-ui.button href="{{ route('billing.index') }}" variant="ghost">Review billing</x-ui.button>
        </div>
    </x-ui.card>

    <details class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
        <summary class="cursor-pointer rounded-lg text-base font-semibold text-stone-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600">Wallet health & recovery tools</summary>
        <p class="mt-2 text-sm text-stone-500">Check saved-card updates or refresh cards after a branding change.</p>
                <div class="mt-4 rounded-xl border border-stone-200 bg-stone-50/80 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-stone-800">Wallet Health</p>
                            <p class="mt-1 text-sm text-stone-600">A quick read on whether saved passes are in a good place before you launch.</p>
                        </div>
                        <x-ui.status-icon-badge
                            :icon="$walletHealth['status_icon'] ?? 'attention'"
                            :label="$walletHealth['status_label']"
                            :tone="$walletHealth['status_tone']"
                        />
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-stone-200 bg-white p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Active cards</p>
                            <p class="mt-1 text-lg font-semibold text-stone-900">{{ $walletHealth['active_cards'] }}</p>
                        </div>
                        <div class="rounded-xl border border-stone-200 bg-white p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Apple registrations</p>
                            <p class="mt-1 text-lg font-semibold text-stone-900">{{ $walletHealth['active_apple_registrations'] }}</p>
                            <p class="mt-1 text-xs text-stone-500">{{ $walletHealth['last_apple_registration_at'] ? 'Last registration '.\Illuminate\Support\Carbon::parse($walletHealth['last_apple_registration_at'])->diffForHumans() : 'No active Apple registrations yet' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 rounded-xl border border-stone-200 bg-white p-4">
                        <p class="text-sm font-semibold text-stone-800">Recommended next action</p>
                        <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ $walletHealth['recommended_action'] }}</p>
                        @if($walletHealth['recent_wallet_syncs']->isNotEmpty())
                            <div class="mt-3 border-t border-stone-200 pt-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Recent wallet sync attempts</p>
                                <ul class="mt-2 space-y-2 text-xs text-stone-600">
                                    @foreach($walletHealth['recent_wallet_syncs'] as $sync)
                                        <li class="flex items-start justify-between gap-3">
                                            <span>{{ $sync->message }}</span>
                                            <span class="whitespace-nowrap font-medium {{ $sync->status === 'success' ? 'text-emerald-700' : 'text-amber-700' }}">{{ ucfirst($sync->status) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>


        <div class="mt-5">
            <h3 class="text-sm font-semibold text-stone-900">Refresh saved cards</h3>
            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-stone-600">Poster and join page previews use your current branding automatically. If cards already in customer wallets look stale, queue a refresh for every card in this store.</p>
            <form class="mt-4" method="POST" action="{{ route('merchant.stores.refresh-wallets', $store) }}">
                @csrf
                <x-ui.button type="submit" variant="secondary">Queue Wallet Refresh for All Cards</x-ui.button>
            </form>
        </div>
    </details>
    <script>
        async function downloadStorePoster(url, filename) {
            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Download failed');
                }

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/pdf')) {
                    window.location.href = url;
                    return;
                }

                const blob = await response.blob();
                const blobUrl = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = blobUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(blobUrl);
            } catch (error) {
                window.location.href = url;
            }
        }

        async function copyToClipboard() {
            const input = document.getElementById('join-link');
            const status = document.getElementById('join-link-status');
            try {
                await navigator.clipboard.writeText(input.value);
                status.textContent = 'Link copied. Ready to share.';
            } catch (error) {
                input.focus();
                input.select();
                input.setSelectionRange(0, input.value.length);
                status.textContent = 'Could not copy automatically. Copy the selected link to share it.';
            }
        }
    </script>
</x-merchant-design-layout>
