<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Scanner') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    
                    @if($stores->isEmpty())
                        <div class="text-center p-6">
                            <p class="mb-4 text-gray-600">You need to create a store before you can scan cards.</p>
                            <a href="{{ route('stores.create') }}" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">
                                Create Store
                            </a>
                        </div>
                    @else
                        <div class="max-w-md mx-auto" x-data="scannerApp()">
                            
                            <!-- Store Selector -->
                            <div class="mb-6">
                                <label for="store_id" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Select Active Store</label>
                                <select id="store_id" x-model="storeId" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Camera Area -->
                            <div id="reader" class="w-full mb-6 bg-black rounded-lg overflow-hidden" style="min-height: 300px;"></div>

                            <!-- Manual Input -->
                            <div class="mb-6">
                                <label for="manual_token" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Or enter token manually</label>
                                <div class="flex gap-2">
                                    <input type="text" id="manual_token" x-model="manualToken" placeholder="e.g. LA:..." class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                    <button @click="stamp(manualToken)" type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">Stamp</button>
                                </div>
                            </div>

                            <!-- Feedback -->
                            <div x-show="message" x-transition class="p-4 mb-4 text-sm rounded-lg" :class="success ? 'text-green-800 bg-green-50 dark:bg-gray-800 dark:text-green-400' : 'text-red-800 bg-red-50 dark:bg-gray-800 dark:text-red-400'" role="alert">
                                <span class="font-medium" x-text="success ? 'Success!' : 'Error!'"></span> <span x-text="message"></span>
                                <template x-if="success && resultData">
                                    <div class="mt-2">
                                        <p><strong>Customer:</strong> <span x-text="resultData.customerLabel"></span></p>
                                        <p><strong>Stamps:</strong> <span x-text="resultData.stampCount"></span> / <span x-text="resultData.rewardTarget"></span></p>
                                    </div>
                                </template>
                            </div>

                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('scannerApp', () => ({
                storeId: '{{ $stores->first()->id ?? "" }}',
                manualToken: '',
                message: '',
                success: false,
                resultData: null,
                isScanning: true,
                html5QrcodeScanner: null,

                init() {
                    this.$nextTick(() => {
                        this.startScanner();
                    });
                },

                startScanner() {
                    const onScanSuccess = (decodedText, decodedResult) => {
                        // Handle the scanned code
                        console.log(`Code matched = ${decodedText}`, decodedResult);
                        this.stamp(decodedText);
                        
                        // Optional: Pause scanning briefly
                        this.html5QrcodeScanner.pause();
                        setTimeout(() => {
                            this.html5QrcodeScanner.resume();
                        }, 2000);
                    };

                    const onScanFailure = (error) => {
                        // handle scan failure, usually better to ignore and keep scanning.
                        // console.warn(`Code scan error = ${error}`);
                    };

                    this.html5QrcodeScanner = new Html5QrcodeScanner(
                        "reader",
                        { fps: 10, qrbox: {width: 250, height: 250} },
                        /* verbose= */ false
                    );
                    this.html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                },

                async stamp(token) {
                    this.message = '';
                    this.success = false;
                    this.resultData = null;

                    if (!token) return;

                    try {
                        const response = await fetch('{{ route("stamp.store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                token: token,
                                store_id: this.storeId
                            })
                        });

                        const data = await response.json();

                        if (response.ok) {
                            this.success = true;
                            this.message = 'Stamp added successfully!';
                            this.resultData = data;
                            this.manualToken = ''; // Clear manual input
                        } else {
                            this.success = false;
                            this.message = data.message || data.errors?.token?.[0] || 'Something went wrong.';
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.success = false;
                        this.message = 'Network error or server issue.';
                    }
                }
            }));
        });
    </script>
    @endpush
</x-app-layout>

