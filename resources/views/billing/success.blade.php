<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Subscription Status') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-center">
                    @if(isset($error))
                        <!-- Error State -->
                        <div class="mb-4">
                            <svg class="mx-auto h-16 w-16 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Issue Detected</h2>
                        <p class="text-gray-600 mb-6">{{ $error }}</p>
                    @elseif(isset($message))
                        <!-- Processing/Async Payment State -->
                        <div class="mb-4">
                            <svg class="mx-auto h-16 w-16 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">
                            @if(isset($isAsyncPayment) && $isAsyncPayment)
                                Payment Processing
                            @else
                                Subscription Activating
                            @endif
                        </h2>
                        <p class="text-gray-600 mb-6">{{ $message }}</p>
                        @if(isset($canRetry) && $canRetry)
                            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-sm text-blue-800 mb-3">
                                    You can manually sync your subscription status using the button below.
                                </p>
                                @if(isset($sessionId))
                                    <form method="POST" action="{{ route('billing.sync') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="session_id" value="{{ $sessionId }}">
                                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 focus:bg-blue-600 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            🔄 Sync Subscription Now
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    @else
                        <!-- Success State (should redirect, but fallback) -->
                        <div class="mb-4">
                            <svg class="mx-auto h-16 w-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Subscription Activated!</h2>
                        <p class="text-gray-600 mb-6">
                            Your Pro plan subscription has been successfully activated. You can now create unlimited loyalty cards.
                        </p>
                    @endif
                    
                    <div class="space-y-3">
                        <a href="{{ route('billing.index', ['refresh' => 1]) }}" 
                           class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 focus:bg-green-600 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Check Subscription Status
                        </a>
                        <a href="{{ route('merchant.dashboard') }}" 
                           class="ml-2 inline-flex items-center px-4 py-2 bg-blue-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 focus:bg-blue-600 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Go to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
