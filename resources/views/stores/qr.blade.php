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
            [
                'label' => 'Plan capacity',
                'ready' => (bool) ($usageStats['can_create_card'] ?? false),
                'hint' => 'New customers can only join while your current plan still allows more cards.',
            ],
        ];
    @endphp

    <div class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
        <x-ui.card class="p-8 flex flex-col items-center justify-center space-y-6">
            <div class="p-4 bg-white rounded-lg shadow-sm border border-stone-200">
                {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(256)->generate($joinUrl) !!}
            </div>

            <p class="text-sm text-stone-600">Scan to join {{ $store->name }}</p>

            <div class="flex flex-col items-center gap-2 w-full">
                <a href="{{ route('merchant.stores.qr.pdf', $store) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-brand-600 text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download PDF (A4 poster)
                </a>
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
                    <span class="inline-flex items-center rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">
                        {{ collect($launchChecks)->where('ready', true)->count() }}/{{ count($launchChecks) }}
                    </span>
                </div>

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

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-ui.button href="{{ route('merchant.stores.edit', $store) }}" variant="secondary" size="sm">
                        Review Store Setup
                    </x-ui.button>
                    <x-ui.button href="{{ route('billing.index') }}" variant="ghost" size="sm">
                        Review Billing
                    </x-ui.button>
                </div>
            </x-ui.card>
        </div>
    </div>

    <script>
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
