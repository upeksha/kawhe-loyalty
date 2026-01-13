<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Subscription Cancelled') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Checkout Cancelled</h2>
                    
                    <p class="text-gray-600 mb-6">
                        Your subscription checkout was cancelled. No charges were made.
                    </p>
                    
                    <div class="space-y-3">
                        <a href="{{ route('billing.index') }}" 
                           class="inline-flex items-center px-4 py-2 bg-blue-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 focus:bg-blue-600 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Back to Billing
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
