<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Usage Stats Banner -->
            @if(isset($usageStats))
                <div class="mb-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-black">
                                    @if($usageStats['is_subscribed'])
                                        <span class="text-green-600">✓ Pro Plan Active</span>
                                    @else
                                        <span class="text-gray-700">Free Plan</span>
                                    @endif
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    Cards issued: <strong>{{ $usageStats['cards_count'] }} / {{ $usageStats['is_subscribed'] ? '∞' : $usageStats['limit'] }}</strong>
                                </p>
                            </div>
                            @if(!$usageStats['is_subscribed'])
                                <a href="{{ route('billing.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 focus:bg-blue-600 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Upgrade
                                </a>
                            @endif
                        </div>
                        
                        @if(!$usageStats['is_subscribed'])
                            <!-- Usage Progress Bar -->
                            <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                                <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" 
                                     style="width: {{ $usageStats['usage_percentage'] }}%"></div>
                            </div>
                            
                            @if($usageStats['cards_count'] >= $usageStats['limit'])
                                <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <p class="text-sm text-yellow-800">
                                        <strong>Limit Reached:</strong> You've reached the free plan limit of {{ $usageStats['limit'] }} cards. 
                                        Existing customers can still use their cards, but new customers cannot join until you upgrade.
                                    </p>
                                </div>
                            @elseif($usageStats['cards_count'] >= ($usageStats['limit'] * 0.8))
                                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <p class="text-sm text-blue-800">
                                        <strong>Almost there:</strong> You're using {{ $usageStats['cards_count'] }} of {{ $usageStats['limit'] }} free cards. 
                                        Consider upgrading to continue adding customers.
                                    </p>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endif
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-bold mb-4 text-black">My Stores</h3>
                        <p class="mb-4 text-black">Manage your coffee shops, rewards, and QR codes.</p>
                        <a href="{{ route('merchant.stores.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 focus:bg-blue-600 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Manage Stores
                        </a>
                        <a href="{{ route('merchant.stores.create') }}" class="ml-2 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Add Store
                        </a>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-bold mb-4 text-black">Customers</h3>
                        <p class="mb-4 text-black">View all customers across your stores.</p>
                        <a href="{{ route('merchant.customers.index') }}" class="inline-flex items-center px-4 py-2 bg-purple-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-600 focus:bg-purple-600 active:bg-purple-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            View Customers
                        </a>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-bold mb-4 text-black">Scanner</h3>
                        <p class="mb-4 text-black">Scan customer QR codes to stamp their cards.</p>
                        <a href="{{ route('merchant.scanner') }}" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 focus:bg-green-600 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Open Scanner
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
