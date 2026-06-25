<x-merchant-layout>
    <x-slot name="header">
        {{ __('Store QR Code') }} - {{ $store->name }}
    </x-slot>

    @php
        $usageStats = app(\App\Services\Billing\UsageService::class)->getUsageStats(request()->user());
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
                'hint' => 'Brand colors help the join page and loyalty card feel complete.',
            ],
            [
                'label' => 'Store logo',
                'ready' => !empty($store->logo_path),
                'hint' => 'Optional, but recommended for a more polished customer-facing card.',
            ],
            [
                'label' => 'Wallet-ready assets',
                'ready' => $walletReady,
                'hint' => 'Recommended if you expect customers to save cards to Apple Wallet or Google Wallet.',
            ],
        ];
        $launchScore = collect($launchChecks)->where('ready', true)->count();
        $launchLabel = $launchScore >= count($launchChecks)
            ? 'Good to launch'
            : ($launchScore >= 3 ? 'Launchable, but could be improved' : 'Needs review');
        $launchTone = $launchScore >= count($launchChecks)
            ? 'bg-emerald-100 text-emerald-700'
            : ($launchScore >= 3 ? 'bg-amber-100 text-amber-700' : 'bg-accent-100 text-accent-700');
    @endphp

    <div class="mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
        <x-ui.card class="p-8 flex flex-col items-center justify-center space-y-6">
            <div class="p-4 bg-white rounded-lg shadow-sm border border-stone-200">
                {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(256)->generate($joinUrl) !!}
            </div>

            <p class="text-sm text-stone-600">Scan to join {{ $store->name }}</p>

            <div class="flex flex-col items-center gap-2 w-full">
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <button type="button" onclick="downloadStorePoster('{{ route('merchant.stores.qr.pdf', $store) }}', '{{ \Illuminate\Support\Str::slug($store->name) }}-join-poster.pdf')" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-brand-600 text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Download PDF (A4 poster)
                    </button>
                    <form method="GET" action="{{ route('merchant.stores.qr.image', $store) }}">
                        <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-stone-100 text-stone-800 hover:bg-stone-200 border border-stone-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stone-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2m4 0h-2m-4 4h6m-6-2h2m2 0h2m-4-4h2m2 0h2" />
                            </svg>
                            Download QR (SVG)
                        </button>
                    </form>
                </div>
                <p class="text-xs text-stone-500">Print or email this poster for your customers to scan and join.</p>
            </div>

            <div class="w-full max-w-md">
                <label for="join-link" class="mb-2 text-sm font-medium text-stone-700 sr-only">Join Link</label>
                <div class="flex gap-2">
                    <x-ui.input type="text" id="join-link" value="{{ $joinUrl }}" readonly class="flex-1" />
                    <x-ui.button onclick="copyToClipboard()" variant="primary" size="md" type="button">
                        Copy
                    </x-ui.button>
                </div>
            </div>

            <x-ui.button href="{{ route('merchant.stores.index') }}" variant="ghost" size="sm">
                ← Back to Stores
            </x-ui.button>
        </x-ui.card>
        </div>

        <div class="lg:col-span-1 space-y-4">
            <x-ui.card class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-stone-900">Store Launch Checklist</h3>
                        <p class="mt-1 text-sm text-stone-600">Review these before you print or publish this QR code.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $launchTone }}">
                        {{ $launchLabel }}
                    </span>
                </div>
                <p class="mt-3 text-sm text-stone-600">{{ $launchScore }}/{{ count($launchChecks) }} launch checks are in a strong place.</p>

                <ul class="mt-4 space-y-3 text-sm">
                    @foreach($launchChecks as $check)
                        <li class="rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium text-stone-800">{{ $check['label'] }}</span>
                                <span class="font-medium {{ $check['ready'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                    {{ $check['ready'] ? 'Ready' : 'Review' }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs leading-relaxed text-stone-500">{{ $check['hint'] }}</p>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4 rounded-xl border border-stone-200 bg-white p-4">
                    <p class="text-sm font-semibold text-stone-800">Before you print or share</p>
                    <ol class="mt-2 space-y-2 text-sm leading-relaxed text-stone-600 list-decimal list-inside">
                        <li>Open the join page once yourself and check the logo, colors, and reward copy.</li>
                        <li>Save one test card to Apple Wallet or Google Wallet to confirm the brand feels right.</li>
                        <li>Confirm the default card reward and branding look right before you print posters.</li>
                    </ol>
                </div>

                <div class="mt-4 rounded-xl border border-stone-200 bg-stone-50/80 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-stone-800">Wallet Health</p>
                            <p class="mt-1 text-sm text-stone-600">A quick read on whether saved passes are in a good place before you launch.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $walletHealth['status_tone'] }}">{{ $walletHealth['status_label'] }}</span>
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

                <div class="mt-4 rounded-xl border border-stone-200 bg-stone-50/80 p-4">
                    <p class="text-sm font-semibold text-stone-800">Recovery Tools</p>
                    <p class="mt-2 text-sm leading-relaxed text-stone-600">
                        Poster and join page previews are always generated from your current branding, so there is no manual rebuild step. If cards already in customer wallets look stale, you can queue a refresh for every card in this store.
                    </p>
                <div class="mt-4 flex flex-wrap gap-2">
                        <x-ui.button href="{{ route('merchant.stores.qr.pdf', ['store' => $store, 'preview' => 1]) }}" variant="ghost" size="sm" target="_blank">
                            Open Poster Preview
                        </x-ui.button>
                        <form method="POST" action="{{ route('merchant.stores.refresh-wallets', $store) }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" size="sm">
                                Queue Wallet Refresh for All Cards
                            </x-ui.button>
                        </form>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-ui.button href="{{ route('merchant.stores.edit', $store) }}" variant="secondary" size="sm">
                        Review Store Setup
                    </x-ui.button>
                    <x-ui.button href="{{ route('billing.index') }}" variant="ghost" size="sm">
                        Review Billing
                    </x-ui.button>
                    <x-ui.button href="{{ $joinUrl }}" variant="ghost" size="sm" target="_blank">
                        Open Join Page
                    </x-ui.button>
                </div>
            </x-ui.card>
        </div>
    </div>

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

        function copyToClipboard() {
            var copyText = document.getElementById("join-link");
            copyText.select();
            copyText.setSelectionRange(0, 99999); // For mobile devices
            navigator.clipboard.writeText(copyText.value).then(function() {
                alert("Copied the link: " + copyText.value);
            }, function(err) {
                console.error('Async: Could not copy text: ', err);
            });
        }
    </script>
</x-merchant-layout>
