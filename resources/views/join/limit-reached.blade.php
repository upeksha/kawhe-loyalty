<x-guest-layout>
    <div class="min-h-screen flex flex-col justify-center py-12 px-6 lg:px-8 bg-gray-50 dark:bg-gray-900">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white dark:bg-gray-800 py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <div class="text-center">
                    <div class="mb-4">
                        <svg class="mx-auto h-16 w-16 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                        Limit Reached
                    </h2>
                    
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        <strong>{{ $store->name }}</strong> has reached the free plan limit for new loyalty cards.
                    </p>
                    
                    <p class="text-sm text-gray-500 dark:text-gray-300 mb-6">
                        Please ask the staff to upgrade their plan to continue adding new customers.
                    </p>
                    
                    <div class="space-y-3">
                        <a href="{{ route('join.index', ['slug' => $store->slug, 't' => $token]) }}" 
                           class="w-full flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            Try Again Later
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
