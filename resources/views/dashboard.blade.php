<x-merchant-layout>
    <x-slot name="header">
        {{ __('Dashboard') }}
    </x-slot>

    <div class="space-y-6">
        <div class="text-brand-700 font-semibold">{{ __("You're logged in") }}</div>
        <!-- Usage Stats Banner -->
        @if(isset($usageStats))
            <x-ui.card class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-stone-900">
                            @if($usageStats['is_subscribed'])
                                <span class="text-brand-600">✓ Pro Plan Active</span>
                            @else
                                <span class="text-stone-700">Free Plan</span>
                            @endif
                        </h3>
                        <p class="text-sm text-stone-600 mt-1">
                            Cards issued: <strong>{{ $usageStats['cards_count'] }} / {{ $usageStats['is_subscribed'] ? '∞' : $usageStats['limit'] }}</strong>
                            @if($usageStats['grandfathered_count'] > 0)
                                <span class="text-xs text-stone-500">({{ $usageStats['grandfathered_count'] }} grandfathered)</span>
                            @endif
                        </p>
                    </div>
                    @if(!$usageStats['is_subscribed'])
                        <x-ui.button href="{{ route('billing.index') }}" variant="primary">
                            Upgrade
                        </x-ui.button>
                    @endif
                </div>
                
                @if(!$usageStats['is_subscribed'])
                    <!-- Usage Progress Bar -->
                    <div class="w-full bg-stone-200 rounded-full h-2.5 mb-2">
                        <div class="bg-brand-600 h-2.5 rounded-full transition-all duration-300" 
                             style="width: {{ $usageStats['usage_percentage'] }}%"></div>
                    </div>
                    
                    @if($usageStats['non_grandfathered_count'] >= $usageStats['limit'])
                        <div class="mt-4 p-4 bg-accent-50 border border-accent-200 rounded-lg">
                            <p class="text-sm text-accent-800">
                                <strong>Limit Reached:</strong> You've reached the free plan limit of {{ $usageStats['limit'] }} cards. 
                                @if($usageStats['grandfathered_count'] > 0)
                                    <br><br>You have {{ $usageStats['grandfathered_count'] }} grandfathered card(s) that remain active from your previous Pro subscription. 
                                    All existing cards continue to work, but new customers cannot join until you upgrade.
                                @else
                                    Existing customers can still use their cards, but new customers cannot join until you upgrade.
                                @endif
                            </p>
                        </div>
                    @elseif($usageStats['has_cancelled_subscription'] && $usageStats['grandfathered_count'] > 0)
                        <div class="mt-4 p-4 bg-brand-50 border border-brand-200 rounded-lg">
                            <p class="text-sm text-brand-800">
                                <strong>Grandfathered Cards:</strong> You have {{ $usageStats['grandfathered_count'] }} card(s) that remain active from your previous Pro subscription. 
                                You can create {{ $usageStats['limit'] - $usageStats['non_grandfathered_count'] }} more card(s) on the free plan.
                            </p>
                        </div>
                    @elseif($usageStats['cards_count'] >= ($usageStats['limit'] * 0.8))
                        <div class="mt-4 p-4 bg-brand-50 border border-brand-200 rounded-lg">
                            <p class="text-sm text-brand-800">
                                <strong>Almost there:</strong> You're using {{ $usageStats['cards_count'] }} of {{ $usageStats['limit'] }} free cards. 
                                Consider upgrading to continue adding customers.
                            </p>
                        </div>
                    @endif
                @endif
            </x-ui.card>
        @endif
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-ui.card class="p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-brand-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold ml-3 text-stone-900">My Stores</h3>
                </div>
                <p class="mb-4 text-stone-600">Manage your coffee shops, rewards, and QR codes.</p>
                <div class="flex gap-2">
                    <x-ui.button href="{{ route('merchant.stores.index') }}" variant="primary" size="sm">
                        Manage Stores
                    </x-ui.button>
                    <x-ui.button href="{{ route('merchant.stores.create') }}" variant="secondary" size="sm" class="ml-5">
                        Add Store
                    </x-ui.button>
                </div>
            </x-ui.card>

            <x-ui.card class="p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-brand-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold ml-3 text-stone-900">Customers</h3>
                </div>
                <p class="mb-4 text-stone-600">View all customers across your stores.</p>
                <x-ui.button href="{{ route('merchant.customers.index') }}" variant="primary" size="sm">
                    View Customers
                </x-ui.button>
            </x-ui.card>

            <x-ui.card class="p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-brand-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold ml-3 text-stone-900">Scanner</h3>
                </div>
                <p class="mb-4 text-stone-600">Scan customer QR codes to stamp their cards.</p>
                <x-ui.button href="{{ route('merchant.scanner') }}" variant="primary" size="sm">
                    Open Scanner
                </x-ui.button>
            </x-ui.card>
        </div>
    </div>
</x-merchant-layout>
