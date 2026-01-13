<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Billing & Subscription') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Error Messages -->
            @if ($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Error</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc list-inside">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <!-- Current Plan Status -->
                    <div class="mb-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Current Plan</h3>
                        @if($stats['is_subscribed'])
                            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-green-800 font-semibold">✓ Pro Plan Active</p>
                                        <p class="text-sm text-green-700 mt-1">Unlimited loyalty cards</p>
                                    </div>
                                    <form method="POST" action="{{ route('billing.portal') }}">
                                        @csrf
                                        <button type="submit" class="px-4 py-2 bg-green-700 text-white rounded-md hover:bg-green-600 text-sm font-medium">
                                            Manage Subscription
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-gray-800 font-semibold">Free Plan</p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            {{ $stats['cards_count'] }} / {{ $stats['limit'] }} cards used
                                            @if($stats['grandfathered_count'] > 0)
                                                <span class="text-xs text-gray-500">({{ $stats['grandfathered_count'] }} grandfathered)</span>
                                            @endif
                                        </p>
                                    </div>
                                    @if($stats['non_grandfathered_count'] >= $stats['limit'])
                                        <div class="text-right">
                                            <p class="text-sm text-red-600 font-semibold mb-2">Limit Reached</p>
                                            <form method="POST" action="{{ route('billing.checkout') }}">
                                                @csrf
                                                <button type="submit" class="px-4 py-2 bg-blue-700 text-white rounded-md hover:bg-blue-600 text-sm font-medium">
                                                    Upgrade Now
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('billing.checkout') }}" id="upgrade-form">
                                            @csrf
                                            <button type="submit" class="px-4 py-2 bg-blue-700 text-white rounded-md hover:bg-blue-600 text-sm font-medium">
                                                Upgrade to Pro
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Usage Statistics -->
                    @if(!$stats['is_subscribed'])
                        <div class="mb-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Usage</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Loyalty Cards Issued</span>
                                    <span class="font-semibold">{{ $stats['cards_count'] }} / {{ $stats['limit'] }}</span>
                                </div>
                                @if($stats['grandfathered_count'] > 0)
                                    <p class="text-xs text-blue-600">
                                        ℹ️ {{ $stats['grandfathered_count'] }} card(s) grandfathered from previous Pro subscription
                                    </p>
                                @endif
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-blue-600 h-3 rounded-full transition-all duration-300"
                                         style="width: {{ $stats['usage_percentage'] }}%"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $stats['limit'] - $stats['non_grandfathered_count'] }} cards remaining on free plan
                                </p>
                            </div>
                        </div>
                    @endif

                    <!-- Subscription Details -->
                    @if($subscription)
                        <div class="mb-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Subscription Details</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-600">
                                    <strong>Status:</strong> 
                                    <span class="capitalize">{{ $subscription->stripe_status }}</span>
                                </p>
                                @if($subscription->ends_at)
                                    <p class="text-sm text-gray-600 mt-2">
                                        <strong>Ends:</strong> {{ $subscription->ends_at->format('M d, Y') }}
                                    </p>
                                @endif
                                <div class="mt-3">
                                    <a href="{{ route('billing.index', ['refresh' => 1]) }}" 
                                       class="text-xs text-blue-600 hover:text-blue-800 underline">
                                        🔄 Refresh Subscription Status
                                    </a>
                                </div>
                            </div>
                        </div>
                    @elseif(isset($debugInfo) && $debugInfo['has_stripe_id'])
                        <div class="mb-6">
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <h3 class="text-sm font-semibold text-yellow-800 mb-2">⚠️ Subscription Not Detected</h3>
                                <p class="text-xs text-yellow-700 mb-3">
                                    Your payment was successful, but the subscription hasn't been synced yet. 
                                    This usually happens if the webhook hasn't been processed. Try the options below:
                                </p>
                                <div class="space-y-2">
                                    <form method="POST" action="{{ route('billing.sync') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-3 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 text-sm font-medium">
                                            🔄 Sync from Stripe Customer
                                        </button>
                                    </form>
                                    <a href="{{ route('billing.index', ['refresh' => 1]) }}" 
                                       class="ml-2 inline-flex items-center px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium">
                                        🔄 Refresh Status
                                    </a>
                                </div>
                                <p class="text-xs text-yellow-600 mt-3">
                                    <strong>Note:</strong> If this persists, check your Stripe Dashboard to verify the subscription exists, 
                                    then contact support with your Stripe customer ID: <code>{{ $debugInfo['stripe_id'] }}</code>
                                </p>
                            </div>
                        </div>
                    @endif
                    
                    <!-- Debug Info (only in development) -->
                    @if(isset($debugInfo) && (app()->environment('local') || config('app.debug')))
                        <div class="border-t pt-6 mt-6">
                            <details class="bg-gray-50 p-4 rounded-lg">
                                <summary class="text-sm font-semibold text-gray-700 cursor-pointer">🔍 Debug Info</summary>
                                <div class="mt-3 text-xs text-gray-600 space-y-1">
                                    <p><strong>Has Stripe ID:</strong> {{ $debugInfo['has_stripe_id'] ? 'Yes' : 'No' }}</p>
                                    <p><strong>Stripe ID:</strong> {{ $debugInfo['stripe_id'] ?? 'N/A' }}</p>
                                    <p><strong>Subscription Exists:</strong> {{ $debugInfo['subscription_exists'] ? 'Yes' : 'No' }}</p>
                                    <p><strong>Subscription Status:</strong> {{ $debugInfo['subscription_status'] ?? 'N/A' }}</p>
                                    <p><strong>Is Subscribed (check):</strong> {{ $debugInfo['is_subscribed_check'] ? 'Yes' : 'No' }}</p>
                                    <p><strong>Subscriptions Count:</strong> {{ $debugInfo['subscriptions_count'] }}</p>
                                </div>
                            </details>
                        </div>
                    @endif

                    <!-- Upgrade Benefits -->
                    @if(!$stats['is_subscribed'])
                        <div class="border-t pt-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Pro Plan Benefits</h3>
                            <ul class="space-y-2 text-sm text-gray-600">
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Unlimited loyalty cards
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    All existing features included
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Cancel anytime
                                </li>
                            </ul>
                        </div>
                    @endif
                    
                    <!-- Debug Info (only show if Stripe not configured) -->
                    @if(empty(config('cashier.key')) || empty(config('cashier.secret')) || empty(config('cashier.price_id')))
                        <div class="border-t pt-6 mt-6">
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-yellow-800 mb-2">⚠️ Stripe Configuration Status</h4>
                                <ul class="text-xs text-yellow-700 space-y-1">
                                    <li>STRIPE_KEY: {{ empty(config('cashier.key')) ? '❌ Not set' : '✅ Set' }}</li>
                                    <li>STRIPE_SECRET: {{ empty(config('cashier.secret')) ? '❌ Not set' : '✅ Set' }}</li>
                                    <li>STRIPE_PRICE_ID: {{ empty(config('cashier.price_id')) ? '❌ Not set' : '✅ Set' }}</li>
                                </ul>
                                <p class="text-xs text-yellow-600 mt-2">
                                    Please configure Stripe in your <code>.env</code> file. See <a href="{{ route('billing.index') }}" class="underline">BILLING_SETUP.md</a> for instructions.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Ensure form submission works
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('upgrade-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('Form submitting to:', form.action);
                    // Let form submit normally
                });
            }
        });
    </script>
</x-app-layout>
