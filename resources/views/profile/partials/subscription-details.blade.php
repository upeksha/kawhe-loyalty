<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Subscription & Billing') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Manage your subscription plan and billing information.') }}
        </p>
    </header>

    <div class="mt-6 space-y-4">
        @if($subscription)
            <!-- Active Subscription -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-green-800">
                            ✓ Pro Plan Active
                        </h3>
                        <p class="text-xs text-green-700 mt-1">
                            Status: <span class="capitalize">{{ $subscription->stripe_status }}</span>
                        </p>
                        @if($subscription->trial_ends_at && $subscription->trial_ends_at->isFuture())
                            <p class="text-xs text-green-700 mt-1">
                                Trial ends: {{ $subscription->trial_ends_at->format('M d, Y') }}
                            </p>
                        @endif
                        @if($subscription->ends_at)
                            <p class="text-xs text-yellow-700 mt-1">
                                Cancels on: {{ $subscription->ends_at->format('M d, Y') }}
                            </p>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-green-700 font-semibold">
                            Unlimited Cards
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <form method="POST" action="{{ route('billing.portal') }}">
                    @csrf
                    <x-primary-button type="submit">
                        {{ __('Manage Subscription') }}
                    </x-primary-button>
                </form>

                @if($subscription->stripe_status === 'active' && !$subscription->ends_at)
                    <form method="POST" action="{{ route('billing.portal') }}" class="inline">
                        @csrf
                        <x-secondary-button type="submit">
                            {{ __('Cancel Subscription') }}
                        </x-secondary-button>
                    </form>
                @endif
            </div>

            <p class="text-xs text-gray-500 mt-2">
                Click "Manage Subscription" to update payment method, view invoices, or cancel your subscription.
            </p>
        @else
            <!-- No Subscription (Free Plan) -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">
                            Free Plan
                        </h3>
                        <p class="text-xs text-gray-600 mt-1">
                            Cards issued: <strong>{{ $usageStats['cards_count'] }} / {{ $usageStats['limit'] }}</strong>
                        </p>
                        @if($usageStats['cards_count'] >= $usageStats['limit'])
                            <p class="text-xs text-red-600 mt-1 font-semibold">
                                ⚠ Limit reached
                            </p>
                        @else
                            <p class="text-xs text-gray-600 mt-1">
                                {{ $usageStats['limit'] - $usageStats['cards_count'] }} cards remaining
                            </p>
                        @endif
                    </div>
                </div>

                @if($usageStats['cards_count'] >= $usageStats['limit'])
                    <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                        <p class="text-xs text-yellow-800 mb-2">
                            You've reached the free plan limit. Upgrade to Pro for unlimited cards.
                        </p>
                        <a href="{{ route('billing.index') }}" 
                           class="inline-flex items-center px-3 py-2 bg-blue-700 text-white rounded-md hover:bg-blue-600 text-xs font-medium">
                            Upgrade to Pro
                        </a>
                    </div>
                @else
                    <div class="mt-4">
                        <a href="{{ route('billing.index') }}" 
                           class="inline-flex items-center px-3 py-2 bg-blue-700 text-white rounded-md hover:bg-blue-600 text-xs font-medium">
                            View Billing & Upgrade
                        </a>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
