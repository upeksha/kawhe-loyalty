    <div class="max-w-2xl mx-auto">
        @if($stores->isEmpty())
            <x-ui.card class="p-6 text-center">
                <p class="mb-4 text-stone-600">You need to create a store before you can scan cards.</p>
                <x-ui.button href="{{ route('merchant.stores.create') }}" variant="primary">
                    Create Store
                </x-ui.button>
            </x-ui.card>
        @else
            <x-ui.card class="p-6">
                <div class="max-w-md mx-auto" x-data="scannerApp()">
                    <!-- Store Selector -->
                    <div class="mb-6">
                        <label for="store_id" class="block mb-2 text-sm font-medium text-stone-700">Select Active Store</label>
                        <select id="store_id" x-model="activeStoreId" class="block w-full rounded-lg border border-stone-300 shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                            <option value="">-- Choose a Store --</option>
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Scanner Controls -->
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs text-stone-600" x-text="cameraStatus"></p>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                x-show="!isScanning"
                                @click="startScanner()"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg bg-brand-600 hover:bg-brand-700 text-white transition"
                            >
                                Start Camera
                            </button>
                            <button
                                type="button"
                                @click="switchCamera()"
                                x-bind:disabled="!canSwitchCamera || !isScanning"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg border transition disabled:opacity-50 disabled:cursor-not-allowed bg-white hover:bg-stone-50 text-stone-800 border-stone-300"
                            >
                                Switch camera
                            </button>
                        </div>
                    </div>

                    <!-- Scanner Container with Cooldown Overlay -->
                    <div class="relative w-full mb-6 bg-black rounded-lg overflow-hidden" style="min-height: 300px; position: relative;">
                        <div id="reader" class="w-full" style="min-height: 300px; width: 100%; position: relative; background: #000;"></div>
                        
                        <!-- Start Camera Button (shown when camera not started) -->
                        <div 
                            x-show="!isScanning && cameraStatus !== 'Scanning…'" 
                            x-cloak
                            class="absolute inset-0 flex items-center justify-center bg-stone-900 bg-opacity-90 z-40 rounded-lg"
                        >
                            <button
                                type="button"
                                @click="startScanner()"
                                class="px-6 py-3 text-base font-medium rounded-lg bg-brand-600 hover:bg-brand-700 text-white transition shadow-lg"
                            >
                                📷 Start Camera
                            </button>
                        </div>
                        
                        <!-- Cooldown Overlay -->
                        <div 
                            x-show="cooldownActive" 
                            x-cloak
                            class="absolute inset-0 bg-black bg-opacity-75 flex flex-col items-center justify-center z-50 rounded-lg"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                        >
                            <div class="text-center">
                                <div class="text-6xl font-bold text-white mb-4" x-text="cooldownSeconds"></div>
                                <p class="text-white text-lg font-semibold">Please wait...</p>
                                <p class="text-gray-300 text-sm mt-2">Scanner will resume automatically</p>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden fallback: upload image -->
                    <div class="mb-6">
                        <button
                            type="button"
                            @click="showUploadFallback = !showUploadFallback"
                            class="text-xs text-stone-500 hover:text-stone-700 underline"
                        >
                            Having trouble? Upload an image of the QR code
                        </button>
                        <div x-show="showUploadFallback" x-cloak class="mt-3">
                            <x-ui.input
                                type="file"
                                accept="image/*"
                                @change="scanFromImageFile($event)"
                            />
                        </div>
                    </div>

                    <!-- Manual Input -->
                    <div class="mb-6">
                        <label for="manual_token" class="block mb-2 text-sm font-medium text-stone-700">Or enter code manually</label>
                        <div class="flex gap-2">
                            <x-ui.input type="text" id="manual_token" x-model="manualToken" placeholder="e.g. A3CX or LA:..." class="flex-1" maxlength="50" />
                            <button @click="handleScan(manualToken)" type="button" class="px-4 py-2 text-sm font-medium rounded-lg bg-brand-600 hover:bg-brand-700 text-white transition focus:outline-none focus:ring-2 focus:ring-brand-500">
                                Scan
                            </button>
                        </div>
                    </div>

                    <!-- Verification Required Modal -->
                    <div x-show="showVerificationModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                        <div class="bg-white rounded-lg p-6 w-full max-w-sm shadow-xl">
                            <div class="text-center mb-6">
                                <div class="text-5xl mb-3">⚠️</div>
                                <h3 class="text-xl font-bold text-stone-900 mb-2">Verification Required</h3>
                                <div class="bg-accent-50 border-l-4 border-accent-500 text-accent-700 p-4 rounded-r mb-4 text-left">
                                    <p class="font-bold mb-1" x-text="verificationData.customer_name || 'Customer'"></p>
                                    <p class="text-sm" x-text="'Email: ' + (verificationData.customer_email || 'Not provided')"></p>
                                    <p class="text-xs mt-2 text-stone-600">
                                        This customer must verify their email address before redeeming rewards.
                                    </p>
                                </div>
                                <p class="text-sm text-stone-600 mb-6">What would you like to do?</p>
                            </div>
                            
                            <div class="space-y-3">
                                <button 
                                    @click="sendVerificationEmail()"
                                    :disabled="sendingVerification"
                                    class="w-full px-4 py-3 text-base font-medium text-white bg-accent-600 rounded-lg hover:bg-accent-700 transition focus:outline-none focus:ring-2 focus:ring-accent-500 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <span x-text="sendingVerification ? 'Sending...' : 'Send Verification Email'"></span>
                                </button>
                                <button 
                                    @click="chooseStampFromVerification()"
                                    class="w-full px-4 py-3 text-base font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition focus:outline-none focus:ring-2 focus:ring-brand-500 flex items-center justify-center gap-2"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    <span>Add Stamp Instead</span>
                                </button>
                            </div>
                            
                            <button 
                                @click="cancelVerificationModal()"
                                class="w-full mt-4 px-4 py-2 text-sm font-medium text-stone-700 bg-stone-100 rounded-lg hover:bg-stone-200 transition"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Choice Modal: When customer has rewards available -->
                    <div x-show="showChoiceModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                        <div class="bg-white rounded-lg p-6 w-full max-w-sm shadow-xl">
                            <div class="text-center mb-6">
                                <div class="text-5xl mb-3">🎁</div>
                                <h3 class="text-xl font-bold text-stone-900 mb-2">Customer Has Rewards Available!</h3>
                                <div class="bg-accent-50 border-l-4 border-accent-500 text-accent-700 p-4 rounded-r mb-4 text-left">
                                    <p class="font-bold mb-1" x-text="previewData.customer_name || 'Customer'"></p>
                                    <p class="text-sm" x-text="'Has ' + previewData.reward_balance + ' ' + (previewData.reward_balance > 1 ? 'rewards' : 'reward') + ' available'"></p>
                                    <p class="text-xs mt-2 text-stone-600" x-text="'Current stamps: ' + previewData.stamp_count + ' / ' + previewData.reward_target"></p>
                                </div>
                                <p class="text-sm text-stone-600 mb-6">What would you like to do?</p>
                            </div>
                            
                            <div class="space-y-3">
                                <button 
                                    @click="chooseRedeem()"
                                    class="w-full px-4 py-3 text-base font-medium text-white bg-accent-600 rounded-lg hover:bg-accent-700 transition focus:outline-none focus:ring-2 focus:ring-accent-500 flex items-center justify-center gap-2"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                                    </svg>
                                    <span>Redeem Reward</span>
                                </button>
                                <button 
                                    @click="chooseStamp()"
                                    class="w-full px-4 py-3 text-base font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition focus:outline-none focus:ring-2 focus:ring-brand-500 flex items-center justify-center gap-2"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    <span>Add Stamp Instead</span>
                                </button>
                            </div>
                            
                            <button 
                                @click="cancelChoiceModal()"
                                class="w-full mt-4 px-4 py-2 text-sm font-medium text-stone-700 bg-stone-100 rounded-lg hover:bg-stone-200 transition"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Modal for Stamp Count / Reward Quantity -->
                    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" style="display: none;">
                        <div class="bg-white rounded-lg p-6 w-full max-w-sm shadow-xl">
                            <!-- Header with mode indicator -->
                            <div class="mb-4">
                                <div 
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold mb-3"
                                    :class="isRedeem ? 'bg-accent-100 text-accent-800' : 'bg-brand-100 text-brand-800'"
                                >
                                    <span x-text="isRedeem ? '🎁 REDEEM' : '➕ STAMP'"></span>
                                </div>
                                <h3 class="text-lg font-bold text-stone-900" x-text="isRedeem ? 'Redeem Reward' : 'Add Stamps'"></h3>
                            </div>
                                    
                            <div x-show="isRedeem" class="mb-4">
                                <div class="bg-accent-50 border-l-4 border-accent-500 text-accent-700 p-4 mb-4 rounded-r" role="alert">
                                    <p class="font-bold flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                                        </svg>
                                        Customer is Redeeming Reward
                                    </p>
                                    <p x-show="rewardBalance > 1" x-text="'Customer has ' + rewardBalance + ' rewards available.'" class="mt-2"></p>
                                    <p x-show="rewardBalance === 1" class="mt-2">Customer has 1 reward available.</p>
                                </div>
                                
                                <!-- Quantity selector for multiple rewards -->
                                <div x-show="rewardBalance > 1">
                                    <h4 class="text-md font-semibold mb-2 text-stone-700">How many rewards to redeem?</h4>
                                    <div class="flex items-center justify-center space-x-4 mb-4">
                                        <button @click="redeemQuantity = Math.max(1, redeemQuantity - 1)" class="w-10 h-10 rounded-full bg-stone-200 flex items-center justify-center text-xl font-bold text-stone-700 hover:bg-stone-300">-</button>
                                        <span class="text-2xl font-bold text-stone-900" x-text="redeemQuantity"></span>
                                        <button @click="redeemQuantity = Math.min(rewardBalance, redeemQuantity + 1)" class="w-10 h-10 rounded-full bg-stone-200 flex items-center justify-center text-xl font-bold text-stone-700 hover:bg-stone-300">+</button>
                                    </div>
                                    <div class="text-center mb-2">
                                        <button @click="redeemQuantity = rewardBalance" class="text-sm text-brand-600 hover:text-brand-700 underline" x-text="'Redeem All (' + rewardBalance + ')'"></button>
                                    </div>
                                    <p class="text-xs text-stone-500 text-center">
                                        <span x-text="'After redeeming ' + redeemQuantity + ', ' + (rewardBalance - redeemQuantity) + ' reward(s) will remain.'"></span>
                                    </p>
                                </div>
                                
                                <!-- Single reward message -->
                                <div x-show="rewardBalance === 1" class="text-sm text-stone-600">
                                    <p>This will redeem 1 reward.</p>
                                </div>
                            </div>

                            <div x-show="!isRedeem">
                                <div class="bg-brand-50 border-l-4 border-brand-500 text-brand-700 p-4 mb-4 rounded-r" role="alert">
                                    <p class="font-bold flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                        Customer is Adding Stamps
                                    </p>
                                </div>
                                <h4 class="text-md font-semibold mb-2 text-stone-700">How many stamps?</h4>
                                <div class="flex items-center justify-center space-x-4 mb-6">
                                    <button @click="stampCount = Math.max(1, stampCount - 1)" class="w-10 h-10 rounded-full bg-stone-200 flex items-center justify-center text-xl font-bold text-stone-700 hover:bg-stone-300">-</button>
                                    <span class="text-2xl font-bold text-stone-900" x-text="stampCount"></span>
                                    <button @click="stampCount++" class="w-10 h-10 rounded-full bg-stone-200 flex items-center justify-center text-xl font-bold text-stone-700 hover:bg-stone-300">+</button>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2">
                                <button @click="cancelActionModal()" class="px-4 py-2 text-sm font-medium text-stone-700 bg-stone-100 rounded-lg hover:bg-stone-200 transition">
                                    Cancel
                                </button>
                                <button 
                                    @click="confirmAction()" 
                                    :class="isRedeem 
                                        ? 'bg-accent-600 hover:bg-accent-700 focus:ring-accent-500' 
                                        : 'bg-brand-600 hover:bg-brand-700 focus:ring-brand-500'"
                                    class="px-4 py-2 text-sm font-medium text-white rounded-lg transition focus:outline-none focus:ring-2" 
                                    x-text="isRedeem ? (rewardBalance > 1 ? 'Redeem ' + redeemQuantity : 'Redeem') : 'Add Stamps'"
                                >
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Cooldown Override Modal -->
                    <div x-show="showCooldownModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                        <div class="bg-white rounded-lg p-6 w-full max-w-sm shadow-xl">
                            <h3 class="text-lg font-bold mb-4 text-stone-900">Cooldown Active</h3>
                            <div class="mb-4">
                                <p class="text-stone-700 mb-2" x-text="`Stamped ${cooldownData?.seconds_since_last || 0}s ago — add another stamp anyway?`"></p>
                                <p class="text-sm text-stone-500">Cooldown: <span x-text="cooldownData?.cooldown_seconds || 30"></span> seconds</p>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button @click="showCooldownModal = false; cooldownData = null" class="px-4 py-2 text-sm font-medium text-stone-700 bg-stone-100 rounded-lg hover:bg-stone-200 transition">
                                    Cancel
                                </button>
                                <button @click="confirmCooldownOverride()" class="px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition focus:outline-none focus:ring-2 focus:ring-brand-500">
                                    Add Anyway
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Store Switched Banner -->
                    <div x-show="storeSwitched" x-transition class="p-4 mb-4 text-sm rounded-lg text-brand-800 bg-brand-50" role="alert">
                        <span class="font-medium">ℹ️ Store Switched</span>
                        <p x-text="'Switched to ' + switchedStoreName + ' for this scan'"></p>
                    </div>

                    <!-- Feedback -->
                    <div x-show="message" x-transition class="p-4 mb-4 text-sm rounded-lg border-l-4" :class="success ? (isRedeem ? 'text-accent-800 bg-accent-50 border-accent-500' : 'text-brand-800 bg-brand-50 border-brand-500') : 'text-red-800 bg-red-50 border-red-500'" role="alert">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="font-medium" x-text="success ? (isRedeem ? '🎁 Reward Redeemed!' : '✅ Stamped!') : '❌ Error!'"></span>
                            <span x-show="success && isRedeem" class="text-xs font-semibold px-2 py-0.5 rounded bg-accent-200 text-accent-900">REDEEM</span>
                            <span x-show="success && !isRedeem" class="text-xs font-semibold px-2 py-0.5 rounded bg-brand-200 text-brand-900">STAMP</span>
                        </div>
                        <span x-text="message"></span>
                        <template x-if="success && resultData">
                            <div class="mt-2">
                                <p><strong>Customer:</strong> <span x-text="resultData.customerLabel"></span></p>
                                <p><strong>Store:</strong> <span x-text="resultData.store_name_used || resultData.storeName"></span></p>
                                <p x-show="!isRedeem"><strong>Stamps:</strong> <span x-text="resultData.stampCount"></span> / <span x-text="resultData.rewardTarget"></span></p>
                                <p x-show="isRedeem && resultData.remaining_rewards !== undefined"><strong>Remaining Rewards:</strong> <span x-text="resultData.remaining_rewards"></span></p>
                            </div>
                        </template>
                    </div>
                </div>
            </x-ui.card>
        @endif
    </div>
