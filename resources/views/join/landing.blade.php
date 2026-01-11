<x-guest-layout>
    <div class="min-h-screen flex flex-col justify-center py-12 px-6 lg:px-8 bg-gray-50 dark:bg-gray-900" x-data="joinLanding()">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="text-center text-3xl font-extrabold text-gray-900 dark:text-white">
                {{ $store->name }}
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
                Join our loyalty program and start earning rewards!
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white dark:bg-gray-800 py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <div class="space-y-6">
                    <!-- Fast Path: Open My Card -->
                    <template x-if="lastToken">
                        <div>
                            <a :href="'/c/' + lastToken" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 ease-in-out">
                                Open My Card
                            </a>
                            <p class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400">
                                Found a card saved on this device.
                            </p>
                        </div>
                    </template>

                    <!-- Returning: I already have a card -->
                    <div>
                        <a href="{{ route('join.existing', ['slug' => $store->slug, 't' => $token]) }}" class="w-full flex justify-center py-3 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            I already have a card
                        </a>
                    </div>

                    <!-- New: Create a new card -->
                    <div>
                        <a href="{{ route('join.show', ['slug' => $store->slug, 't' => $token]) }}" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            Create a new card
                        </a>
                    </div>
                    
                    <template x-if="lastToken">
                        <div class="text-center">
                            <button @click="clearLastCard()" class="text-xs text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 underline">
                                Use a different card/email
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('joinLanding', () => ({
                lastToken: localStorage.getItem('kawhe_last_card_{{ $store->id }}'),
                
                clearLastCard() {
                    localStorage.removeItem('kawhe_last_card_{{ $store->id }}');
                    this.lastToken = null;
                }
            }));
        });
    </script>
    @endpush
</x-guest-layout>
