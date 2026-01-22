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
</x-app-layout>

