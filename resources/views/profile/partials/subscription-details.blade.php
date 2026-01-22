<section>
    <header>
        <h2 class="text-lg font-medium text-stone-900">
            {{ __('Subscription & Billing') }}
        </h2>

        <p class="mt-1 text-sm text-stone-600">
            {{ __('Manage your subscription plan and billing information.') }}
        </p>
    </header>

    <div class="mt-6 space-y-4">
        @if($subscription)
            <!-- Active Subscription -->
            <div class="bg-brand-50 border border-brand-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-brand-800">
                            ✓ Pro Plan Active
                        </h3>
                        <p class="text-xs text-brand-700 mt-1">
                            Status: <span class="capitalize">{{ $subscription->stripe_status }}</span>
                        </p>
                        @if($subscription->trial_ends_at && $subscription->trial_ends_at->isFuture())
                            <p class="text-xs text-brand-700 mt-1">
                                Trial ends: {{ $subscription->trial_ends_at->format('M d, Y') }}
                            </p>
                        @endif
                        @if($subscription->ends_at)
                            <p class="text-xs text-accent-700 mt-1">
                                Cancels on: {{ $subscription->ends_at->format('M d, Y') }}
                            </p>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-brand-700 font-semibold">
                            Unlimited Cards
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <form method="POST" action="{{ route('billing.portal') }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary">
                        {{ __('Manage Subscription') }}
                    </x-ui.button>
                </form>

                @if($subscription->stripe_status === 'active' && !$subscription->ends_at)
                    <form method="POST" action="{{ route('billing.portal') }}" class="inline">
                        @csrf
                        <x-ui.button type="submit" variant="secondary">
                            {{ __('Cancel Subscription') }}
                        </x-ui.button>
                    </form>
                @endif
            </div>

            <p class="text-xs text-stone-500 mt-2">
                Click "Manage Subscription" to update payment method, view invoices, or cancel your subscription.
            </p>
        @else
            <!-- No Subscription (Free Plan) -->
            <div class="bg-stone-50 border border-stone-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-stone-800">
                            Free Plan
                        </h3>
                        <p class="text-xs text-stone-600 mt-1">
                            Cards issued: <strong>{{ $usageStats['cards_count'] }} / {{ $usageStats['limit'] }}</strong>
                            @if($usageStats['grandfathered_count'] > 0)
                                <span class="text-xs text-stone-500">({{ $usageStats['grandfathered_count'] }} grandfathered)</span>
                            @endif
                        </p>
                        @if($usageStats['non_grandfathered_count'] >= $usageStats['limit'])
                            <p class="text-xs text-red-600 mt-1 font-semibold">
                                ⚠ Limit reached
                            </p>
                        @else
                            <p class="text-xs text-stone-600 mt-1">
                                {{ $usageStats['limit'] - $usageStats['non_grandfathered_count'] }} cards remaining
                            </p>
                        @endif
                        @if($usageStats['has_cancelled_subscription'] && $usageStats['grandfathered_count'] > 0)
                            <p class="text-xs text-brand-600 mt-1">
                                ℹ️ {{ $usageStats['grandfathered_count'] }} card(s) remain active from your previous Pro subscription
                            </p>
                        @endif
                    </div>
                </div>

                @if($usageStats['non_grandfathered_count'] >= $usageStats['limit'])
                    <div class="mt-4 p-3 bg-accent-50 border border-accent-200 rounded">
                        <p class="text-xs text-accent-800 mb-2">
                            You've reached the free plan limit. 
                            @if($usageStats['grandfathered_count'] > 0)
                                Your {{ $usageStats['grandfathered_count'] }} grandfathered card(s) remain active, but you cannot create new cards. 
                            @endif
                            Upgrade to Pro for unlimited cards.
                        </p>
                        <x-ui.button href="{{ route('billing.index') }}" variant="primary" size="sm">
                            Upgrade to Pro
                        </x-ui.button>
                    </div>
                @else
                    <div class="mt-4">
                        <x-ui.button href="{{ route('billing.index') }}" variant="primary" size="sm">
                            View Billing & Upgrade
                        </x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
