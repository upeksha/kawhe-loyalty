<x-merchant-layout>
    <x-slot name="header">
        {{ __('Subscription Status') }}
    </x-slot>

    <div class="max-w-2xl mx-auto">
        <x-ui.card class="p-6 text-center">
                    @if(isset($error))
                        <!-- Error State -->
                        <div class="mb-4">
                            <svg class="mx-auto h-16 w-16 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-stone-900 mb-2">Issue Detected</h2>
                        <p class="text-stone-600 mb-6">{{ $error }}</p>
                    @elseif(isset($message))
                        <!-- Processing/Async Payment State -->
                        <div class="mb-4">
                            <svg class="mx-auto h-16 w-16 text-accent-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-stone-900 mb-2">
                            @if(isset($isAsyncPayment) && $isAsyncPayment)
                                Payment Processing
                            @else
                                Subscription Activating
                            @endif
                        </h2>
                        <p class="text-stone-600 mb-6">{{ $message }}</p>
                        @if(isset($canRetry) && $canRetry)
                            <div class="mb-6 p-4 bg-accent-50 border border-accent-200 rounded-lg">
                                <p class="text-sm text-accent-800 mb-3">
                                    You can manually sync your subscription status using the button below.
                                </p>
                                @if(isset($sessionId))
                                    <form method="POST" action="{{ route('billing.sync') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="session_id" value="{{ $sessionId }}">
                                        <x-ui.button type="submit" variant="primary">
                                            🔄 Sync Subscription Now
                                        </x-ui.button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    @else
                        <!-- Success State (should redirect, but fallback) -->
                        <div class="mb-4">
                            <svg class="mx-auto h-16 w-16 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-stone-900 mb-2">Subscription Activated!</h2>
                        <p class="text-stone-600 mb-6">
                            Your Pro plan subscription has been successfully activated. You can now create unlimited loyalty cards.
                        </p>
                    @endif
                    
                    <div class="space-y-3">
                        <x-ui.button href="{{ route('billing.index', ['refresh' => 1]) }}" variant="primary">
                            Check Subscription Status
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.dashboard') }}" variant="primary">
                            Go to Dashboard
                        </x-ui.button>
                    </div>
        </x-ui.card>
    </div>
</x-merchant-layout>
