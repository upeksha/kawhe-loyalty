<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Scanner') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    
                    @if($stores->isEmpty())
                        <div class="text-center p-6">
                            <p class="mb-4 text-gray-600">You need to create a store before you can scan cards.</p>
                            <a href="{{ route('merchant.stores.create') }}" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">
                                Create Store
                            </a>
                        </div>
                    @else
                        <div class="max-w-md mx-auto" x-data="scannerApp()">
                            
                            <!-- Store Selector -->
                            <div class="mb-6">
                                <label for="store_id" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Select Active Store</label>
                                <select id="store_id" x-model="activeStoreId" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                    <option value="">-- Choose a Store --</option>
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div id="reader" class="w-full mb-6 bg-black rounded-lg overflow-hidden" style="min-height: 300px; color: white;"></div>

                            <!-- Manual Input -->
                            <div class="mb-6">
                                <label for="manual_token" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Or enter token manually</label>
                                <div class="flex gap-2">
                                    <input type="text" id="manual_token" x-model="manualToken" placeholder="e.g. LA:..." class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                    <button @click="handleScan(manualToken)" type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">Scan</button>
                                </div>
                            </div>

                            <!-- Modal for Stamp Count / Reward Quantity -->
                            <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" style="display: none;">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-sm">
                                    <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-white">Action Required</h3>
                                    
                                    <div x-show="isRedeem" class="mb-4">
                                        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
                                            <p class="font-bold">Redeem Reward?</p>
                                            <p x-show="rewardBalance > 1" x-text="'Customer has ' + rewardBalance + ' rewards available.'"></p>
                                            <p x-show="rewardBalance === 1">Customer has 1 reward available.</p>
                                        </div>
                                        
                                        <!-- Quantity selector for multiple rewards -->
                                        <div x-show="rewardBalance > 1">
                                            <h4 class="text-md font-semibold mb-2 text-gray-700 dark:text-gray-300">How many rewards to redeem?</h4>
                                            <div class="flex items-center justify-center space-x-4 mb-4">
                                                <button @click="redeemQuantity = Math.max(1, redeemQuantity - 1)" class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xl font-bold text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">-</button>
                                                <span class="text-2xl font-bold text-gray-900 dark:text-white" x-text="redeemQuantity"></span>
                                                <button @click="redeemQuantity = Math.min(rewardBalance, redeemQuantity + 1)" class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xl font-bold text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">+</button>
                                            </div>
                                            <div class="text-center mb-2">
                                                <button @click="redeemQuantity = rewardBalance" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline" x-text="'Redeem All (' + rewardBalance + ')'"></button>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                                                <span x-text="'After redeeming ' + redeemQuantity + ', ' + (rewardBalance - redeemQuantity) + ' reward(s) will remain.'"></span>
                                            </p>
                                        </div>
                                        
                                        <!-- Single reward message -->
                                        <div x-show="rewardBalance === 1" class="text-sm text-gray-600 dark:text-gray-400">
                                            <p>This will redeem 1 reward.</p>
                                        </div>
                                    </div>

                                    <div x-show="!isRedeem">
                                        <h4 class="text-md font-semibold mb-2 text-gray-700 dark:text-gray-300">How many stamps?</h4>
                                        <div class="flex items-center justify-center space-x-4 mb-6">
                                            <button @click="stampCount = Math.max(1, stampCount - 1)" class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xl font-bold text-gray-700 dark:text-gray-300">-</button>
                                            <span class="text-2xl font-bold text-gray-900 dark:text-white" x-text="stampCount"></span>
                                            <button @click="stampCount++" class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xl font-bold text-gray-700 dark:text-gray-300">+</button>
                                        </div>
                                    </div>

                                    <div class="flex justify-end space-x-2">
                                        <button @click="showModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">Cancel</button>
                                        <button @click="confirmAction()" class="px-4 py-2 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800" x-text="isRedeem ? (rewardBalance > 1 ? 'Redeem ' + redeemQuantity : 'Redeem') : 'Add Stamps'"></button>
                                    </div>
                                </div>
                            </div>

                            <!-- Cooldown Override Modal -->
                            <div x-show="showCooldownModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-sm">
                                    <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-white">Cooldown Active</h3>
                                    <div class="mb-4">
                                        <p class="text-gray-700 dark:text-gray-300 mb-2" x-text="`Stamped ${cooldownData?.seconds_since_last || 0}s ago — add another stamp anyway?`"></p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Cooldown: <span x-text="cooldownData?.cooldown_seconds || 30"></span> seconds</p>
                                    </div>
                                    <div class="flex justify-end space-x-2">
                                        <button @click="showCooldownModal = false; cooldownData = null" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">Cancel</button>
                                        <button @click="confirmCooldownOverride()" class="px-4 py-2 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">Add Anyway</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Store Switched Banner -->
                            <div x-show="storeSwitched" x-transition class="p-4 mb-4 text-sm rounded-lg text-blue-800 bg-blue-50 dark:bg-blue-900 dark:text-blue-200" role="alert">
                                <span class="font-medium">ℹ️ Store Switched</span>
                                <p x-text="'Switched to ' + switchedStoreName + ' for this scan'"></p>
                            </div>

                            <!-- Feedback -->
                            <div x-show="message" x-transition class="p-4 mb-4 text-sm rounded-lg" :class="success ? 'text-green-800 bg-green-50 dark:bg-gray-800 dark:text-green-400' : 'text-red-800 bg-red-50 dark:bg-gray-800 dark:text-red-400'" role="alert">
                                <span class="font-medium" x-text="success ? 'Success!' : 'Error!'"></span> <span x-text="message"></span>
                                <template x-if="success && resultData">
                                    <div class="mt-2">
                                        <p><strong>Customer:</strong> <span x-text="resultData.customerLabel"></span></p>
                                        <p><strong>Store:</strong> <span x-text="resultData.store_name_used || resultData.storeName"></span></p>
                                        <p><strong>Stamps:</strong> <span x-text="resultData.stampCount"></span> / <span x-text="resultData.rewardTarget"></span></p>
                                    </div>
                                </template>
                            </div>

                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <style>
        [x-cloak] { display: none !important; }
    </style>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('scannerApp', () => ({
                activeStoreId: '{{ $stores->first()->id ?? "" }}',
                manualToken: '',
                message: '',
                success: false,
                resultData: null,
                isScanning: true,
                html5QrcodeScanner: null,
                showModal: false,
                stampCount: 1,
                pendingToken: null,
                isRedeem: false,
                rewardBalance: 1, // Default to 1 for single reward
                redeemQuantity: 1, // Quantity to redeem (default 1)
                storeSwitched: false,
                switchedStoreName: '',
                showCooldownModal: false,
                cooldownData: null,
                pendingCooldownToken: null,
                pendingCooldownCount: 1,

                init() {
                    this.$nextTick(() => {
                        this.startScanner();
                    });
                },

                startScanner() {
                    const onScanSuccess = (decodedText, decodedResult) => {
                        console.log(`Code matched = ${decodedText}`, decodedResult);
                        this.handleScan(decodedText);
                        
                        this.html5QrcodeScanner.pause();
                    };

                    const onScanFailure = (error) => {
                        // handle scan failure
                    };

                    this.html5QrcodeScanner = new Html5QrcodeScanner(
                        "reader",
                        { fps: 10, qrbox: {width: 250, height: 250} },
                        /* verbose= */ false
                    );
                    this.html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                },

                async handleScan(token) {
                    if (!token) return;
                    
                    // Note: store_id is now optional - backend will auto-detect from token
                    // But we still require it for redeem operations
                    if (token.startsWith('REDEEM:')) {
                        if (!this.activeStoreId) {
                            this.success = false;
                            this.message = 'Please select a store first for redemption.';
                            return;
                        }
                        this.isRedeem = true;
                        this.pendingToken = token;
                        
                        // Fetch reward balance to show quantity selector if needed
                        await this.fetchRedeemInfo(token);
                        
                        this.showModal = true;
                    } else {
                        this.isRedeem = false;
                        this.showStampModal(token);
                    }
                },
                
                async fetchRedeemInfo(token) {
                    try {
                        const response = await fetch('{{ route("redeem.info") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                token: token,
                                store_id: Number(this.activeStoreId)
                            })
                        });

                        const data = await response.json();
                        
                        if (data.success) {
                            this.rewardBalance = data.reward_balance || 1;
                            this.redeemQuantity = Math.min(this.rewardBalance, 1); // Default to 1, but can't exceed balance
                        } else {
                            // Fallback to 1 if fetch fails
                            this.rewardBalance = 1;
                            this.redeemQuantity = 1;
                        }
                    } catch (error) {
                        console.error('Error fetching redeem info:', error);
                        // Fallback to 1 if fetch fails
                        this.rewardBalance = 1;
                        this.redeemQuantity = 1;
                    }
                },

                showStampModal(token) {
                    if (!token) return;
                    this.pendingToken = token;
                    this.stampCount = 1;
                    this.showModal = true;
                },

                confirmAction() {
                    this.showModal = false;
                    if (this.isRedeem) {
                        this.redeem(this.pendingToken, this.redeemQuantity);
                    } else {
                        this.stamp(this.pendingToken, this.stampCount);
                    }
                    
                    // Reset redeem quantity for next time
                    this.redeemQuantity = 1;
                    
                    // Resume scanning after a delay if using scanner
                    if (this.html5QrcodeScanner) {
                        setTimeout(() => {
                            this.html5QrcodeScanner.resume();
                        }, 2000);
                    }
                },

                async redeem(token, quantity = 1) {
                    this.message = '';
                    this.success = false;
                    this.resultData = null;

                    try {
                        const response = await fetch('{{ route("redeem.store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                token: token,
                                store_id: Number(this.activeStoreId),
                                quantity: quantity
                            })
                        });

                        const text = await response.text();
                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            console.error('Failed to parse JSON response:', text);
                            throw new Error('Server returned invalid response');
                        }

                        if (response.ok) {
                            this.success = true;
                            this.message = data.message || 'Reward redeemed successfully!';
                            this.resultData = { 
                                customerLabel: data.customerLabel,
                                remaining_rewards: data.receipt?.remaining_rewards || 0
                            };
                            
                            // Show remaining rewards if any
                            if (data.receipt && data.receipt.remaining_rewards > 0) {
                                this.message += ` (${data.receipt.remaining_rewards} reward(s) remaining)`;
                            }
                        } else {
                            this.success = false;
                            // Use improved error messages from server
                            this.message = data.message || data.errors?.token?.[0] || data.errors?.quantity?.[0] || 'Redemption failed. Please try again.';
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.success = false;
                        this.message = error.message || 'Network error or server issue.';
                    }
                },

                async stamp(token, count = 1, overrideCooldown = false) {
                    this.message = '';
                    this.success = false;
                    this.resultData = null;

                    if (!token) return;

                    try {
                        const requestBody = {
                            token: token,
                            count: count,
                            override_cooldown: overrideCooldown
                        };
                        
                        // Include store_id if available (for backwards compatibility)
                        // Backend will auto-detect if not provided
                        if (this.activeStoreId) {
                            requestBody.store_id = Number(this.activeStoreId);
                        }

                        const response = await fetch('{{ route("stamp.store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify(requestBody)
                        });

                        const text = await response.text();
                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            console.error('Failed to parse JSON response:', text);
                            throw new Error('Server returned invalid response');
                        }

                        // Handle different response statuses
                        if (data.status === 'cooldown') {
                            // Show cooldown override modal
                            this.cooldownData = data;
                            this.pendingCooldownToken = token;
                            this.pendingCooldownCount = count;
                            this.showCooldownModal = true;
                            // Clear any error message since we're showing the modal
                            this.message = '';
                            this.success = false;
                            return;
                        }

                        if (data.status === 'duplicate') {
                            // Show subtle duplicate message
                            this.success = false;
                            this.message = 'Duplicate scan ignored';
                            this.storeSwitched = false;
                            return;
                        }

                        if (response.ok && (data.status === 'success' || data.success)) {
                            this.success = true;
                            this.message = data.message || `${count} stamp(s) added successfully!`;
                            this.resultData = data;
                            this.manualToken = ''; // Clear manual input
                            this.showCooldownModal = false; // Close cooldown modal if open
                            
                            // Handle store switching
                            if (data.store_switched && data.store_id_used) {
                                this.storeSwitched = true;
                                this.switchedStoreName = data.store_name_used || data.storeName;
                                this.activeStoreId = data.store_id_used.toString();
                                
                                // Auto-hide banner after 5 seconds
                                setTimeout(() => {
                                    this.storeSwitched = false;
                                }, 5000);
                            } else {
                                this.storeSwitched = false;
                            }
                            
                            // Show additional info if available
                            if (data.stampsRemaining !== undefined && data.stampsRemaining > 0) {
                                this.message += ` (${data.stampsRemaining} more needed for reward)`;
                            } else if (data.rewardAvailable) {
                                this.message += ' 🎉 Reward unlocked!';
                            }
                        } else {
                            this.success = false;
                            this.storeSwitched = false;
                            // Use improved error messages from server
                            this.message = data.message || data.errors?.token?.[0] || 'Something went wrong. Please try again.';
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.success = false;
                        this.message = error.message || 'Network error or server issue.';
                    }
                },

                confirmCooldownOverride() {
                    this.showCooldownModal = false;
                    if (this.pendingCooldownToken) {
                        this.stamp(this.pendingCooldownToken, this.pendingCooldownCount, true);
                        this.pendingCooldownToken = null;
                        this.pendingCooldownCount = 1;
                    }
                }
            }));
        });
    </script>
    @endpush
</x-app-layout>

