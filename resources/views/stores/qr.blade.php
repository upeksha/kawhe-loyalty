<x-merchant-layout>
    <x-slot name="header">
        {{ __('Store QR Code') }} - {{ $store->name }}
    </x-slot>

    <div class="max-w-2xl mx-auto">
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

