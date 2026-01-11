<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Store QR Code') }} - {{ $store->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 flex flex-col items-center justify-center space-y-6">

                    <div class="p-4 bg-white rounded-lg shadow-md">
                        {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(256)->generate($joinUrl) !!}
                    </div>

                    <p class="text-sm text-gray-500">Scan to join {{ $store->name }}</p>

                    <div class="w-full max-w-md">
                        <label for="join-link" class="mb-2 text-sm font-medium text-gray-900 sr-only dark:text-white">Join Link</label>
                        <div class="relative">
                            <input type="text" id="join-link" class="block w-full p-4 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" value="{{ $joinUrl }}" readonly>
                            <button onclick="copyToClipboard()" class="text-white absolute end-2.5 bottom-2.5 bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">Copy</button>
                        </div>
                    </div>

                    <a href="{{ route('merchant.stores.index') }}" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Back to Stores</a>
                </div>
            </div>
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
</x-app-layout>

