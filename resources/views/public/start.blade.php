<x-guest-layout>
    <div class="min-h-screen flex flex-col justify-center py-12 px-6 lg:px-8 bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">
        <div class="sm:mx-auto sm:w-full sm:max-w-2xl">
            <div class="text-center">
                <h1 class="text-5xl font-extrabold text-gray-900 dark:text-white mb-4">
                    Kawhe Loyalty
                </h1>
                <p class="text-xl text-gray-600 dark:text-gray-300 mb-8">
                    Create your café loyalty QR program in minutes
                </p>
            </div>

            <div class="bg-white dark:bg-gray-800 py-12 px-8 shadow-2xl sm:rounded-2xl">
                <div class="space-y-6">
                    <div class="text-center mb-8">
                        <svg class="mx-auto h-24 w-24 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                        </svg>
                    </div>

                    <div class="space-y-4">
                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">✓ Digital Stamp Cards</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">No more paper cards. Customers scan QR codes to earn stamps.</p>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">✓ Instant Setup</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Create your store, print your QR code, and start rewarding customers.</p>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">✓ Real-Time Updates</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Customers see their progress instantly. No app download required.</p>
                        </div>
                    </div>

                    <div class="pt-6 space-y-4">
                        <a href="{{ route('register') }}" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            Create Free Account
                        </a>
                        <a href="{{ route('login') }}" class="w-full flex justify-center py-3 px-4 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-base font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            Log In
                        </a>
                    </div>
                </div>
            </div>

            <p class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
                Already have an account? <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-500">Sign in here</a>
            </p>
        </div>
    </div>
</x-guest-layout>
