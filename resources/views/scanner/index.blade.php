<x-merchant-layout>
    <x-slot name="header">
        {{ __('Scanner') }}
    </x-slot>

    @php
        $appleStoreUrl = config('services.merchant_app.apple_store_url');
        $googlePlayUrl = config('services.merchant_app.google_play_url');
    @endphp

    <div class="mx-auto">
        @if($stores->isEmpty())
            <x-ui.card class="p-6 text-center">
                <p class="mb-4 text-stone-600">You need to create a store before you can scan cards.</p>
                <x-ui.button href="{{ route('merchant.stores.create') }}" variant="primary">
                    Create Store
                </x-ui.button>
            </x-ui.card>
        @else
            <x-ui.card class="mb-6 overflow-hidden border border-stone-200/80 bg-gradient-to-br from-[#f3efe7] via-white to-[#edf4eb] p-5 sm:p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#4f7d54]">Merchant App</p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight text-stone-900 sm:text-2xl">
                            Scan and redeem faster with the Kawhe Merchant app
                        </h2>
                        <p class="mt-3 text-sm leading-6 text-stone-600">
                            Give your team the quickest way to scan loyalty cards, switch stores, and redeem rewards on the go. The mobile app is the easiest setup for busy counter staff.
                        </p>
                    </div>

                    <div class="flex w-full max-w-md flex-col gap-3 sm:flex-row">
                        @if($appleStoreUrl)
                            <a
                                href="{{ $appleStoreUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex flex-1 items-center justify-center rounded-2xl bg-white p-1.5 transition hover:bg-[#edf4eb]"
                            >
                                <img
                                    src="{{ asset('images/store-badges/app-store-light-en.png') }}"
                                    alt="Download on the App Store"
                                    class="h-14 w-auto"
                                >
                            </a>
                        @endif

                        @if($googlePlayUrl)
                            <a
                                href="{{ $googlePlayUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex flex-1 items-center justify-center rounded-2xl bg-white p-1.5 transition hover:bg-[#edf4eb]"
                            >
                                <img
                                    src="{{ asset('images/store-badges/google-play-light-en.png') }}"
                                    alt="Get it on Google Play"
                                    class="h-14 w-auto"
                                >
                            </a>
                        @endif
                    </div>
                </div>

                @if(!$appleStoreUrl || !$googlePlayUrl)
                    <p class="mt-4 text-xs text-stone-500">
                        Set <code>MERCHANT_APP_APPLE_STORE_URL</code> and <code>MERCHANT_APP_GOOGLE_PLAY_URL</code> in production to show both live store links here.
                    </p>
                @endif
            </x-ui.card>

            <x-ui.card class="p-4 sm:p-6">
                <div class="max-w-md mx-auto" x-data="scannerApp()" @keydown.escape.window="handleEscape()">
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
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                        <p class="text-xs sm:text-sm text-stone-600" x-text="cameraStatus"></p>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-semibold"
                                :class="cooldownActive ? 'bg-amber-100 text-amber-800' : (isProcessingScan ? 'bg-brand-100 text-brand-800' : 'bg-emerald-100 text-emerald-800')"
                                x-text="cooldownActive ? 'Cooldown' : (isProcessingScan ? 'Processing' : 'Ready')"
                            ></span>
                            <button
                                type="button"
                                x-show="!isScanning"
                                @click="startScanner()"
                                :disabled="isProcessingScan"
                                class="px-3 py-2 text-xs font-medium rounded-lg bg-brand-600 hover:bg-brand-700 text-white transition"
                            >
                                Start Camera
                            </button>
                            <button
                                type="button"
                                @click="switchCamera()"
                                x-bind:disabled="!canSwitchCamera || !isScanning || isProcessingScan"
                                class="px-3 py-2 text-xs font-medium rounded-lg border transition disabled:opacity-50 disabled:cursor-not-allowed bg-white hover:bg-stone-50 text-stone-800 border-stone-300"
                            >
                                Switch camera
                            </button>
                        </div>
                    </div>

                    <div
                        x-show="failureContext.type"
                        x-cloak
                        class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 sm:p-4"
                        role="alert"
                        aria-live="assertive"
                    >
                        <p class="text-sm font-semibold text-amber-900" x-text="failureContext.title"></p>
                        <p class="mt-1 text-xs text-amber-800" x-text="failureContext.detail"></p>
                        <div class="mt-3 flex flex-col sm:flex-row sm:flex-wrap gap-2">
                            <button
                                type="button"
                                @click="retryFromFailure()"
                                class="w-full sm:w-auto px-3 py-2 text-xs font-medium rounded-lg bg-amber-700 hover:bg-amber-800 text-white transition"
                            >
                                Try again
                            </button>
                            <button
                                type="button"
                                @click="openUploadFallback()"
                                class="w-full sm:w-auto px-3 py-2 text-xs font-medium rounded-lg bg-white border border-amber-300 text-amber-900 hover:bg-amber-100 transition"
                            >
                                Upload QR image
                            </button>
                            <button
                                type="button"
                                @click="focusManualEntry()"
                                class="w-full sm:w-auto px-3 py-2 text-xs font-medium rounded-lg bg-white border border-amber-300 text-amber-900 hover:bg-amber-100 transition"
                            >
                                Enter code manually
                            </button>
                        </div>
                    </div>

                    <!-- Scanner Container with Cooldown Overlay -->
                    <div class="relative w-full mb-6 bg-black rounded-lg overflow-hidden" style="min-height: 280px; position: relative;">
                        <div id="reader" class="w-full" style="min-height: 280px; width: 100%; position: relative; background: #000;"></div>
                        
                        <!-- Start Camera Button (shown when camera not started) -->
                        <div 
                            x-show="!isScanning && cameraStatus !== 'Scanning…'" 
                            x-cloak
                            class="absolute inset-0 flex items-center justify-center bg-stone-900 bg-opacity-90 z-40 rounded-lg"
                        >
                            <button
                                type="button"
                                @click="startScanner()"
                                :disabled="isProcessingScan"
                                class="px-6 py-3 text-base font-medium rounded-lg bg-brand-600 hover:bg-brand-700 text-white transition shadow-lg"
                            >
                                📷 Start Camera
                            </button>
                        </div>

                        <div
                            x-show="isProcessingScan"
                            x-cloak
                            class="absolute inset-0 bg-black/60 flex flex-col items-center justify-center z-50 rounded-lg"
                        >
                            <div class="h-9 w-9 rounded-full border-2 border-white/40 border-t-white animate-spin"></div>
                            <p class="mt-3 text-sm font-medium text-white">Processing scan…</p>
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
                                x-ref="uploadInput"
                            />
                        </div>
                    </div>

                    <!-- Manual Input -->
                    <div class="mb-6">
                        <label for="manual_token" class="block mb-2 text-sm font-medium text-stone-700">Or enter code manually</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <x-ui.input type="text" id="manual_token" x-model="manualToken" placeholder="e.g. A3CX or LA:..." class="flex-1" maxlength="50" />
                            <button @click="handleScan(manualToken)" :disabled="isProcessingScan || !manualToken" type="button" class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium rounded-lg bg-brand-600 hover:bg-brand-700 text-white transition focus:outline-none focus:ring-2 focus:ring-brand-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                Scan
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-stone-500">Scanner pauses briefly while each scan is validated to prevent double actions.</p>
                    </div>

                    <!-- Modal for Stamp Count / Reward Quantity -->
                    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-3 sm:p-4" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="action-modal-title" @click.self="cancelActionModal()">
                        <div x-ref="actionModalPanel" tabindex="-1" @keydown.tab="trapFocus($event, 'action')" class="bg-white rounded-lg p-4 sm:p-6 w-full max-w-sm shadow-xl max-h-[90vh] overflow-y-auto">
                            <!-- Header with mode indicator -->
                            <div class="mb-4">
                                <div 
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold mb-3"
                                    :class="isRedeem ? 'bg-accent-100 text-accent-800' : 'bg-brand-100 text-brand-800'"
                                >
                                    <span x-text="isRedeem ? '🎁 REDEEM' : '➕ STAMP'"></span>
                                </div>
                                <h3 id="action-modal-title" class="text-lg font-bold text-stone-900" x-text="isRedeem ? 'Redeem Reward' : 'Add Stamps'"></h3>
                                <div x-show="showModeToggle" class="mt-3 p-1 rounded-lg bg-stone-100 grid grid-cols-2 gap-1">
                                    <button
                                        type="button"
                                        @click="chooseStamp()"
                                        class="px-3 py-2 text-xs font-semibold rounded-md transition"
                                        :class="!isRedeem ? 'bg-white text-brand-700 shadow-sm' : 'text-stone-600 hover:text-stone-900'"
                                    >
                                        Add Stamp
                                    </button>
                                    <button
                                        type="button"
                                        @click="chooseRedeem()"
                                        class="px-3 py-2 text-xs font-semibold rounded-md transition"
                                        :class="isRedeem ? 'bg-white text-accent-700 shadow-sm' : 'text-stone-600 hover:text-stone-900'"
                                    >
                                        Redeem Reward
                                    </button>
                                </div>
                            </div>
                                    
                            <div x-show="verificationRequired" class="mb-4">
                                <div class="bg-accent-50 border-l-4 border-accent-500 text-accent-700 p-4 mb-4 rounded-r" role="alert">
                                    <p class="font-bold mb-1" x-text="verificationData.customer_name || 'Customer'"></p>
                                    <p class="text-sm" x-text="'Email: ' + (verificationData.customer_email || 'Not provided')"></p>
                                    <p class="text-xs mt-2 text-stone-600">
                                        This customer must verify their email address before redeeming rewards.
                                    </p>
                                </div>
                                <p class="text-sm text-stone-600">Send a verification email or switch to stamp mode.</p>
                            </div>

                            <div x-show="isRedeem && !verificationRequired" class="mb-4">
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
                                        <button
                                            @click="redeemQuantity = Math.max(1, redeemQuantity - 1)"
                                            :disabled="redeemQuantity <= 1"
                                            aria-label="Decrease rewards to redeem"
                                            class="w-11 h-11 rounded-full bg-stone-200 flex items-center justify-center text-xl font-bold text-stone-700 hover:bg-stone-300 disabled:opacity-40 disabled:cursor-not-allowed"
                                        >-</button>
                                        <span class="text-2xl font-bold text-stone-900" x-text="redeemQuantity"></span>
                                        <button
                                            @click="redeemQuantity = Math.min(rewardBalance, redeemQuantity + 1)"
                                            :disabled="redeemQuantity >= rewardBalance"
                                            aria-label="Increase rewards to redeem"
                                            class="w-11 h-11 rounded-full bg-stone-200 flex items-center justify-center text-xl font-bold text-stone-700 hover:bg-stone-300 disabled:opacity-40 disabled:cursor-not-allowed"
                                        >+</button>
                                    </div>
                                    <div class="text-center mb-2">
                                        <button @click="redeemQuantity = rewardBalance" class="text-sm text-brand-600 hover:text-brand-700 underline" x-text="'Redeem All (' + rewardBalance + ')'"></button>
                                    </div>
                                    <p class="text-xs text-stone-500 text-center">
                                        <span x-text="'After redeeming ' + redeemQuantity + ', ' + (rewardBalance - redeemQuantity) + ' reward(s) will remain.'"></span>
                                    </p>
                                    <p x-show="redeemQuantity >= rewardBalance" class="text-xs text-amber-700 text-center mt-2">This uses all available rewards.</p>
                                </div>
                                
                                <!-- Single reward message -->
                                <div x-show="rewardBalance === 1" class="text-sm text-stone-600">
                                    <p>This will redeem 1 reward.</p>
                                </div>
                            </div>

                            <div x-show="!isRedeem && !verificationRequired">
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
                                    <button
                                        @click="stampCount = Math.max(1, stampCount - 1)"
                                        :disabled="stampCount <= 1"
                                        aria-label="Decrease stamps"
                                        class="w-11 h-11 rounded-full bg-stone-200 flex items-center justify-center text-xl font-bold text-stone-700 hover:bg-stone-300 disabled:opacity-40 disabled:cursor-not-allowed"
                                    >-</button>
                                    <span class="text-2xl font-bold text-stone-900" x-text="stampCount"></span>
                                    <button
                                        @click="stampCount++"
                                        aria-label="Increase stamps"
                                        class="w-11 h-11 rounded-full bg-stone-200 flex items-center justify-center text-xl font-bold text-stone-700 hover:bg-stone-300"
                                    >+</button>
                                </div>
                                <div class="flex flex-wrap justify-center gap-2 mb-4">
                                    <button type="button" @click="stampCount = 1" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-stone-300 bg-white hover:bg-stone-50">1</button>
                                    <button type="button" @click="stampCount = 2" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-stone-300 bg-white hover:bg-stone-50">2</button>
                                    <button type="button" @click="stampCount = 3" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-stone-300 bg-white hover:bg-stone-50">3</button>
                                    <button type="button" @click="stampCount = Math.max(1, lastStampCount)" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-stone-300 bg-white hover:bg-stone-50">Use last (<span x-text="lastStampCount"></span>)</button>
                                </div>
                            </div>

                            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                                <button x-ref="actionCancelBtn" @click="cancelActionModal()" class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-stone-700 bg-stone-100 rounded-lg hover:bg-stone-200 transition">
                                    Cancel
                                </button>
                                <button
                                    x-show="verificationRequired"
                                    @click="sendVerificationEmail()"
                                    :disabled="sendingVerification"
                                    class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-accent-600 rounded-lg hover:bg-accent-700 transition focus:outline-none focus:ring-2 focus:ring-accent-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                    x-text="sendingVerification ? 'Sending...' : 'Send Verification Email'"
                                >
                                </button>
                                <button 
                                    x-show="!verificationRequired"
                                    @click="confirmAction()" 
                                    :class="isRedeem 
                                        ? 'bg-accent-600 hover:bg-accent-700 focus:ring-accent-500' 
                                        : 'bg-brand-600 hover:bg-brand-700 focus:ring-brand-500'"
                                    class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white rounded-lg transition focus:outline-none focus:ring-2" 
                                    x-text="isRedeem ? (rewardBalance > 1 ? 'Redeem ' + redeemQuantity : 'Redeem') : 'Add Stamps'"
                                >
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Cooldown Override Modal -->
                    <div x-show="showCooldownModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="cooldown-modal-title" @click.self="closeCooldownModal()">
                        <div x-ref="cooldownModalPanel" tabindex="-1" @keydown.tab="trapFocus($event, 'cooldown')" class="bg-white rounded-lg p-4 sm:p-6 w-full max-w-sm shadow-xl">
                            <h3 id="cooldown-modal-title" class="text-lg font-bold mb-4 text-stone-900">Cooldown Active</h3>
                            <div class="mb-4">
                                <p class="text-stone-700 mb-2" x-text="`Stamped ${cooldownData?.seconds_since_last || 0}s ago — add another stamp anyway?`"></p>
                                <p class="text-sm text-stone-500">Cooldown: <span x-text="cooldownData?.cooldown_seconds || 5"></span> seconds</p>
                            </div>
                            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                                <button x-ref="cooldownCancelBtn" @click="closeCooldownModal()" class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-stone-700 bg-stone-100 rounded-lg hover:bg-stone-200 transition">
                                    Cancel
                                </button>
                                <button @click="confirmCooldownOverride()" class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition focus:outline-none focus:ring-2 focus:ring-brand-500">
                                    Add Anyway
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Store Switched Banner -->
                    <div x-show="storeSwitched" x-transition class="p-4 mb-4 text-sm rounded-lg text-brand-800 bg-brand-50" role="status" aria-live="polite">
                        <span class="font-medium">ℹ️ Store Switched</span>
                        <p x-text="'Switched to ' + switchedStoreName + ' for this scan'"></p>
                    </div>

                    <!-- Feedback -->
                    <div x-show="message" x-transition class="p-4 mb-4 text-sm rounded-lg border-l-4" :class="success ? (isRedeem ? 'text-accent-800 bg-accent-50 border-accent-500' : 'text-brand-800 bg-brand-50 border-brand-500') : 'text-red-800 bg-red-50 border-red-500'" role="status" :aria-live="success ? 'polite' : 'assertive'">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="font-medium" x-text="success ? (isRedeem ? '🎁 Reward Redeemed!' : '✅ Stamped!') : '❌ Error!'"></span>
                            <span x-show="success && isRedeem" class="text-xs font-semibold px-2 py-0.5 rounded bg-accent-200 text-accent-900">REDEEM</span>
                            <span x-show="success && !isRedeem" class="text-xs font-semibold px-2 py-0.5 rounded bg-brand-200 text-brand-900">STAMP</span>
                        </div>
                        <span x-text="message"></span>
                        <template x-if="success && resultData">
                            <div class="mt-2 rounded-lg border border-white/40 bg-white/60 p-3">
                                <p><strong>Customer:</strong> <span x-text="resultData.customerLabel"></span></p>
                                <p><strong>Store:</strong> <span x-text="resultData.store_name_used || resultData.storeName"></span></p>
                                <p x-show="!isRedeem"><strong>Stamps:</strong> <span x-text="resultData.stampCount"></span> / <span x-text="resultData.rewardTarget"></span></p>
                                <p x-show="isRedeem && resultData.remaining_rewards !== undefined"><strong>Remaining Rewards:</strong> <span x-text="resultData.remaining_rewards"></span></p>
                                <p x-show="cooldownActive" class="text-xs mt-2 text-stone-600">Next scan ready in <span x-text="cooldownSeconds"></span>s</p>
                                <div class="mt-3 flex flex-col sm:flex-row gap-2">
                                    <button type="button" @click="repeatLastAction()" class="w-full sm:w-auto px-3 py-2 text-xs font-medium rounded-lg bg-white border border-stone-300 text-stone-800 hover:bg-stone-50">Repeat same action</button>
                                    <button type="button" @click="clearResultAndResume()" class="w-full sm:w-auto px-3 py-2 text-xs font-medium rounded-lg bg-white border border-stone-300 text-stone-800 hover:bg-stone-50">Scan next card</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </x-ui.card>
        @endif
    </div>

    @push('scripts')
    <style>
        [x-cloak] { display: none !important; }
        
        /* Reader container */
        #reader {
            position: relative !important;
            width: 100% !important;
            min-height: 280px !important;
            background: #000 !important;
            overflow: hidden !important;
        }
        
        /* Video element styling - let html5-qrcode handle positioning */
        #reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block !important;
            background: #000 !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
        }
        
        /* Canvas overlay for QR detection */
        #reader canvas {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            pointer-events: none !important;
        }
        
        /* Hide duplicate elements */
        #reader > *:not(video):not(canvas) {
            display: none !important;
        }
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
                showModal: false,
                showModeToggle: false, // Toggle between stamp/redeem in a single modal
                verificationRequired: false, // Verification state inside the unified action modal
                verificationData: null, // Data for verification modal
                sendingVerification: false, // Loading state for sending verification email
                previewData: null, // Data from preview endpoint
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
                cooldownActive: false,
                cooldownSeconds: 5,
                cooldownInterval: null,
                failureContext: { type: '', title: '', detail: '' },
                lastFocusedElement: null,
                activeDialog: null,
                lastStampCount: 1,
                lastRedeemQuantity: 1,
                lastAction: null,

                init() {
                    // Don't auto-start on iOS Safari - requires user gesture
                    // Check if iOS Safari
                    const isIOSSafari = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                    
                    if (!isIOSSafari) {
                        // Auto-start on other browsers
                        this.$nextTick(() => {
                            this.startScanner();
                        });
                    } else {
                        // iOS Safari: Show start button
                        this.cameraStatus = 'Tap "Start Camera" to begin scanning';
                        this.isScanning = false;
                    }

                    this.$watch('showModal', (isOpen) => {
                        if (isOpen) {
                            this.openDialog('action');
                        } else if (this.activeDialog === 'action') {
                            this.activeDialog = null;
                            this.restoreFocus();
                        }
                    });

                    this.$watch('showCooldownModal', (isOpen) => {
                        if (isOpen) {
                            this.openDialog('cooldown');
                        } else if (this.activeDialog === 'cooldown') {
                            this.activeDialog = null;
                            this.restoreFocus();
                        }
                    });
                },

                                // Camera / scanner state
                                html5QrCode: null,
                                cameras: [],
                                activeCameraId: null,
                                cameraStatus: 'Starting camera…',
                                showUploadFallback: false,
                                isProcessingScan: false,

                                get canSwitchCamera() {
                                    return (this.cameras && this.cameras.length > 1);
                                },

                                setFailureContext(type, title, detail) {
                                    this.failureContext = { type, title, detail };
                                },

                                clearFailureContext() {
                                    this.failureContext = { type: '', title: '', detail: '' };
                                },

                                retryFromFailure() {
                                    if (this.failureContext.type === 'network') {
                                        this.resumeScanner();
                                        this.cameraStatus = 'Scanning…';
                                        this.clearFailureContext();
                                        return;
                                    }

                                    this.startScanner();
                                },

                                openUploadFallback() {
                                    this.showUploadFallback = true;
                                    this.$nextTick(() => this.$refs.uploadInput?.focus());
                                },

                                focusManualEntry() {
                                    this.$nextTick(() => document.getElementById('manual_token')?.focus());
                                },

                                openDialog(dialogName) {
                                    this.lastFocusedElement = document.activeElement;
                                    this.activeDialog = dialogName;
                                    this.$nextTick(() => this.focusFirstElement(dialogName));
                                },

                                focusFirstElement(dialogName) {
                                    const panel = dialogName === 'action'
                                        ? this.$refs.actionModalPanel
                                        : this.$refs.cooldownModalPanel;
                                    if (!panel) return;

                                    const preferred = dialogName === 'action'
                                        ? this.$refs.actionCancelBtn
                                        : this.$refs.cooldownCancelBtn;
                                    if (preferred) {
                                        preferred.focus({ preventScroll: true });
                                        return;
                                    }

                                    const focusables = this.getFocusableElements(panel);
                                    if (focusables.length > 0) {
                                        focusables[0].focus({ preventScroll: true });
                                    } else {
                                        panel.focus({ preventScroll: true });
                                    }
                                },

                                getFocusableElements(container) {
                                    if (!container) return [];
                                    const selector = [
                                        'a[href]',
                                        'button:not([disabled])',
                                        'textarea:not([disabled])',
                                        'input:not([type="hidden"]):not([disabled])',
                                        'select:not([disabled])',
                                        '[tabindex]:not([tabindex="-1"])',
                                    ].join(',');

                                    return Array.from(container.querySelectorAll(selector))
                                        .filter((el) => el.offsetParent !== null);
                                },

                                trapFocus(event, dialogName) {
                                    if (event.key !== 'Tab') return;

                                    const panel = dialogName === 'action'
                                        ? this.$refs.actionModalPanel
                                        : this.$refs.cooldownModalPanel;
                                    if (!panel) return;

                                    const focusables = this.getFocusableElements(panel);
                                    if (focusables.length === 0) {
                                        event.preventDefault();
                                        panel.focus({ preventScroll: true });
                                        return;
                                    }

                                    const first = focusables[0];
                                    const last = focusables[focusables.length - 1];
                                    const active = document.activeElement;

                                    if (event.shiftKey && active === first) {
                                        event.preventDefault();
                                        last.focus({ preventScroll: true });
                                    } else if (!event.shiftKey && active === last) {
                                        event.preventDefault();
                                        first.focus({ preventScroll: true });
                                    }
                                },

                                restoreFocus() {
                                    if (this.lastFocusedElement && typeof this.lastFocusedElement.focus === 'function') {
                                        this.lastFocusedElement.focus({ preventScroll: true });
                                    }
                                    this.lastFocusedElement = null;
                                },

                                handleEscape() {
                                    if (this.showCooldownModal) {
                                        this.closeCooldownModal();
                                        return;
                                    }

                                    if (this.showModal) {
                                        this.cancelActionModal();
                                    }
                                },

                                closeCooldownModal() {
                                    this.showCooldownModal = false;
                                    this.cooldownData = null;
                                },

                                async startScanner() {
                                    // Always stop scanner first if it's running
                                    try {
                                        await this.stopScanner();
                                    } catch (e) {
                                        // Ignore errors when stopping (might not be running)
                                    }
                                    
                                    // Small delay to ensure cleanup completes
                                    await new Promise(resolve => setTimeout(resolve, 100));
                                    
                                    // Ensure container is clean
                                    const readerElement = document.getElementById('reader');
                                    if (readerElement) {
                                        readerElement.innerHTML = '';
                                    }
                                    
                                    this.cameraStatus = 'Requesting camera permission…';
                                    this.isScanning = true;

                                    try {
                                        // Always create a new instance to avoid state issues
                                        this.html5QrCode = new Html5Qrcode('reader');

                                        // Detect iOS Safari
                                        const isIOSSafari = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                                        
                                        if (isIOSSafari) {
                                            // iOS Safari: Need to enumerate cameras and use deviceId
                                            await this.loadCameras();
                                            
                                            if (this.cameras.length === 0) {
                                                throw new Error('No cameras found');
                                            }
                                            
                                            // Try stored camera first, or pick preferred (back camera)
                                            let cameraId = localStorage.getItem('kawhe_scanner_camera_id');
                                            if (!cameraId || !this.cameras.find(c => c.id === cameraId)) {
                                                cameraId = this.pickPreferredCameraId(this.cameras) || this.cameras[this.cameras.length - 1].id;
                                            }
                                            
                                            // iOS Safari specific config - use viewfinder for better video display
                                            const readerEl = document.getElementById('reader');
                                            const containerWidth = readerEl.offsetWidth || 300;
                                            const containerHeight = readerEl.offsetHeight || 300;
                                            
                                            const config = { 
                                                fps: 10,
                                                viewfinderWidth: containerWidth,
                                                viewfinderHeight: containerHeight,
                                                qrbox: function(viewfinderWidth, viewfinderHeight) {
                                                    const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                                                    const qrboxSize = Math.floor(minEdge * 0.7);
                                                    return { width: qrboxSize, height: qrboxSize };
                                                },
                                                aspectRatio: 1.0
                                            };
                                            
                                            await this.html5QrCode.start(
                                                { deviceId: { exact: cameraId } },
                                                config,
                                                (decodedText) => this.onScanSuccess(decodedText),
                                                (errorMessage) => {
                                                    if (errorMessage && !errorMessage.includes('No MultiFormat Readers') && !errorMessage.includes('QR code parse error')) {
                                                        console.warn('Unexpected scan error:', errorMessage);
                                                    }
                                                }
                                            );
                                            
                                            this.activeCameraId = cameraId;
                                            localStorage.setItem('kawhe_scanner_camera_id', cameraId);
                                            
                                            // Force video element to be visible on iOS Safari
                                            setTimeout(() => {
                                                const video = readerEl.querySelector('video');
                                                if (video) {
                                                    video.style.width = '100%';
                                                    video.style.height = '100%';
                                                    video.style.objectFit = 'cover';
                                                    video.style.display = 'block';
                                                    video.style.background = '#000';
                                                }
                                            }, 200);
                                        } else {
                                            // Non-iOS: Use facingMode (simpler and works well)
                                            const config = { 
                                                fps: 10, 
                                                qrbox: { width: 250, height: 250 },
                                                aspectRatio: 1.0
                                            };
                                            
                                            await this.html5QrCode.start(
                                                { facingMode: 'environment' },
                                                config,
                                                (decodedText) => this.onScanSuccess(decodedText),
                                                (errorMessage) => {
                                                    if (errorMessage && !errorMessage.includes('No MultiFormat Readers') && !errorMessage.includes('QR code parse error')) {
                                                        console.warn('Unexpected scan error:', errorMessage);
                                                    }
                                                }
                                            );
                                            
                                            // Load cameras for switching (optional)
                                            await this.loadCameras();
                                        }
                                        
                                        this.cameraStatus = 'Scanning…';
                                        this.isScanning = true;
                                        this.clearFailureContext();
                                    } catch (e) {
                                        console.error('Failed to start scanner:', e);
                                        this.isScanning = false;
                                        
                                        // Better error messages - safely check e.message
                                        const errorMessage = e?.message || e?.toString() || '';
                                        const errorName = e?.name || '';
                                        
                                        if (errorName === 'NotAllowedError' || errorMessage.includes('permission')) {
                                            this.cameraStatus = 'Camera permission denied. Please allow camera access in Safari settings.';
                                            this.setFailureContext(
                                                'permission',
                                                'Camera permission is blocked',
                                                'Allow camera access in your browser settings, then tap Try again.'
                                            );
                                        } else if (errorName === 'NotFoundError' || errorMessage.includes('camera')) {
                                            this.cameraStatus = 'No camera found. Please check your device.';
                                            this.setFailureContext(
                                                'no_camera',
                                                'No camera detected',
                                                'Use upload or manual entry, or connect a camera and retry.'
                                            );
                                        } else if (errorMessage.includes('scan is ongoing')) {
                                            // Scanner already running - try stopping and restarting
                                            this.cameraStatus = 'Restarting camera…';
                                            setTimeout(() => this.startScanner(), 500);
                                        } else {
                                            this.cameraStatus = 'Camera unavailable. Tap "Start Camera" to try again.';
                                            this.setFailureContext(
                                                'camera_unavailable',
                                                'Camera is unavailable',
                                                'Close other camera apps and try again. You can still upload or enter code manually.'
                                            );
                                        }
                                    }
                                },

                                async loadCameras() {
                                    try {
                                        const cams = await Html5Qrcode.getCameras();
                                        this.cameras = cams || [];
                                    } catch (e) {
                                        console.warn('Unable to enumerate cameras:', e);
                                        this.cameras = [];
                                    }
                                },

                                pickPreferredCameraId(cameras) {
                                    if (!cameras || cameras.length === 0) return null;
                                    if (cameras.length === 1) return cameras[0].id;

                                    // Prefer a back camera if labels are available (labels often blank until permission).
                                    const byLabel = cameras.find(c => (c.label || '').toLowerCase().includes('back'))
                                        || cameras.find(c => (c.label || '').toLowerCase().includes('rear'))
                                        || cameras.find(c => (c.label || '').toLowerCase().includes('environment'));
                                    if (byLabel) return byLabel.id;

                                    // Heuristic: on many devices, the last camera is the back camera.
                                    return cameras[cameras.length - 1].id;
                                },

                                async switchCamera() {
                                    if (!this.canSwitchCamera) return;
                                    
                                    // Load cameras if not loaded
                                    if (this.cameras.length === 0) {
                                        await this.loadCameras();
                                    }
                                    
                                    if (this.cameras.length < 2) {
                                        this.cameraStatus = 'Only one camera available';
                                        return;
                                    }
                                    
                                    const ids = this.cameras.map(c => c.id);
                                    const currentIdx = this.activeCameraId ? ids.indexOf(this.activeCameraId) : -1;
                                    const nextIdx = (currentIdx >= 0 ? currentIdx + 1 : 1) % ids.length;
                                    const nextId = ids[nextIdx];
                                    
                                    this.cameraStatus = 'Switching camera…';
                                    
                                    try {
                                        // Stop current scanner
                                        await this.stopScanner();
                                        
                                        // Small delay to ensure cleanup
                                        await new Promise(resolve => setTimeout(resolve, 200));
                                        
                                        // Ensure container is clean
                                        const readerElement = document.getElementById('reader');
                                        if (readerElement) {
                                            readerElement.innerHTML = '';
                                        }
                                        
                                        // Create new instance
                                        this.html5QrCode = new Html5Qrcode('reader');
                                        
                                        // Start with new camera using deviceId
                                        const config = { 
                                            fps: 10, 
                                            qrbox: { width: 250, height: 250 },
                                            aspectRatio: 1.0
                                        };
                                        
                                        await this.html5QrCode.start(
                                            { deviceId: { exact: nextId } },
                                            config,
                                            (decodedText) => this.onScanSuccess(decodedText),
                                            (errorMessage) => {
                                                if (errorMessage && !errorMessage.includes('No MultiFormat Readers') && !errorMessage.includes('QR code parse error')) {
                                                    console.warn('Unexpected scan error:', errorMessage);
                                                }
                                            }
                                        );
                                        
                                        this.activeCameraId = nextId;
                                        localStorage.setItem('kawhe_scanner_camera_id', nextId);
                                        this.cameraStatus = 'Scanning…';
                                        this.isScanning = true;
                                        this.clearFailureContext();
                                    } catch (e) {
                                        console.error('Failed to switch camera:', e);
                                        this.cameraStatus = 'Could not switch camera.';
                                        this.setFailureContext(
                                            'camera_unavailable',
                                            'Could not switch camera',
                                            'Try again, or continue using upload/manual entry.'
                                        );
                                        // Try to restart with original method
                                        try {
                                            await this.startScanner();
                                        } catch (e2) {
                                            this.isScanning = false;
                                        }
                                    }
                                },

                                async stopScanner() {
                                    if (!this.html5QrCode) {
                                        this.isScanning = false;
                                        return;
                                    }
                                    
                                    // stop() throws if not running; guard with try
                                    try {
                                        await this.html5QrCode.stop();
                                    } catch (e) {
                                        // Scanner might not be running - try to clear instead
                                        try {
                                            await this.html5QrCode.clear();
                                        } catch (clearError) {
                                            // If clear also fails, just reset the instance
                                            this.html5QrCode = null;
                                        }
                                    }
                                    
                                    // Manually clear the container to remove any leftover video/canvas elements
                                    const readerElement = document.getElementById('reader');
                                    if (readerElement) {
                                        // Remove all video and canvas elements
                                        readerElement.querySelectorAll('video, canvas').forEach(el => el.remove());
                                        // Clear innerHTML to ensure clean state
                                        readerElement.innerHTML = '';
                                    }
                                    
                                    this.isScanning = false;
                                },

                                pauseScanner() {
                                    try {
                                        if (this.html5QrCode && typeof this.html5QrCode.pause === 'function') {
                                            this.html5QrCode.pause(true);
                                        }
                                    } catch (e) {
                                        // ignore
                                    }
                                },

                                resumeScanner() {
                                    try {
                                        if (this.html5QrCode && typeof this.html5QrCode.resume === 'function') {
                                            this.html5QrCode.resume();
                                        }
                                    } catch (e) {
                                        // ignore
                                    }
                                },

                                startCooldown(seconds = 5) {
                                    // Clear any existing cooldown
                                    if (this.cooldownInterval) {
                                        clearInterval(this.cooldownInterval);
                                    }
                                    
                                    // Pause scanner during cooldown
                                    this.pauseScanner();
                                    
                                    // Set cooldown state
                                    this.cooldownActive = true;
                                    this.cooldownSeconds = seconds;
                                    
                                    // Start countdown
                                    this.cooldownInterval = setInterval(() => {
                                        this.cooldownSeconds--;
                                        
                                        if (this.cooldownSeconds <= 0) {
                                            // Cooldown finished
                                            clearInterval(this.cooldownInterval);
                                            this.cooldownInterval = null;
                                            this.cooldownActive = false;
                                            this.cooldownSeconds = 5;
                                            
                                            // Resume scanner
                                            this.resumeScanner();
                                        }
                                    }, 1000);
                                },

                                async scanFromImageFile(event) {
                                    const file = event?.target?.files?.[0];
                                    if (!file) return;

                                    try {
                                        this.pauseScanner();
                                        // scanFile works even if the camera is running; we pause to avoid double processing.
                                        const decodedText = await this.html5QrCode.scanFile(file, true);
                                        await this.onScanSuccess(decodedText);
                                    } catch (e) {
                                        console.error('Image scan failed:', e);
                                        this.success = false;
                                        this.message = 'Could not read a QR code from that image.';
                                        this.setFailureContext(
                                            'invalid_image',
                                            'Could not read that image',
                                            'Try a clearer QR image, or enter the code manually.'
                                        );
                                        // Resume camera scanning
                                        this.resumeScanner();
                                    } finally {
                                        // allow re-uploading the same file
                                        event.target.value = '';
                                    }
                                },

                                async onScanSuccess(decodedText) {
                                    // Don't process scans during cooldown
                                    if (this.cooldownActive) {
                                        console.log('Scan ignored: cooldown active');
                                        return;
                                    }
                                    
                                    if (this.isProcessingScan) return;
                                    this.isProcessingScan = true;
                                    this.pauseScanner();
                                    try {
                                        await this.handleScan(decodedText);
                                    } finally {
                                        // handleScan opens modals; scanner will be resumed on confirm/cancel.
                                        this.isProcessingScan = false;
                                    }
                                },

                async handleScan(token) {
                    if (!token) return;
                    
                    this.pendingToken = token;
                    
                    // Always preview first to check if customer has rewards
                    try {
                        const previewResponse = await fetch('{{ route("scanner.preview") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                token: token,
                                store_id: this.activeStoreId || null
                            })
                        });

                        const previewResult = await previewResponse.json();
                        
                        if (!previewResult.success) {
                            this.success = false;
                            this.message = previewResult.message || 'Could not process QR code. Please try again.';
                            this.setFailureContext(
                                'scan_invalid',
                                'QR could not be processed',
                                'Confirm the QR is from this app, then scan again or use manual entry.'
                            );
                            this.resumeScanner();
                            return;
                        }
                        
                        // Store preview data
                        this.previewData = previewResult;
                        this.clearFailureContext();
                        
                        // If customer has rewards, open the action modal directly and allow mode switching
                        if (previewResult.has_rewards && previewResult.reward_balance > 0) {
                            this.showModeToggle = true;
                            this.chooseStamp();
                        } else {
                            // No rewards available, go straight to stamp modal
                            this.showModeToggle = false;
                            this.isRedeem = false;
                            this.showStampModal(token);
                        }
                    } catch (error) {
                        console.error('Error previewing scan:', error);
                        this.success = false;
                        this.message = 'Network error. Please try again.';
                        this.setFailureContext(
                            'network',
                            'Network issue while validating scan',
                            'Check your connection, then retry.'
                        );
                        this.resumeScanner();
                    }
                },
                
                chooseRedeem() {
                    if (!this.previewData) {
                        this.success = false;
                        this.message = 'Scan a customer card first.';
                        return;
                    }

                    // User chose to redeem
                    this.isRedeem = true;
                    this.rewardBalance = this.previewData.reward_balance;
                    this.redeemQuantity = Math.min(this.rewardBalance, Math.max(1, this.lastRedeemQuantity || 1));
                    
                    // Determine the redeem token to use
                    let redeemToken = null;
                    if (this.previewData.is_redeem_qr) {
                        // Already scanned a redeem QR - extract token
                        const originalToken = this.pendingToken;
                        if (originalToken.startsWith('LR:')) {
                            redeemToken = originalToken.substring(3).trim();
                        } else if (originalToken.startsWith('REDEEM:')) {
                            redeemToken = originalToken.substring(7).trim();
                        } else {
                            redeemToken = originalToken;
                        }
                    } else if (this.previewData.redeem_token) {
                        // Scanned a stamp QR but customer has rewards - use their redeem token
                        redeemToken = this.previewData.redeem_token;
                    }
                    
                    if (!redeemToken) {
                        this.success = false;
                        this.message = 'Unable to process redemption. Please scan the redeem QR code from the customer\'s card.';
                        this.resumeScanner();
                        return;
                    }
                    
                    // Update pending token to use redeem token format
                    this.pendingToken = 'LR:' + redeemToken;
                    
                    // Ensure store is set (required for redeem)
                    if (!this.activeStoreId && this.previewData.store_id) {
                        this.activeStoreId = this.previewData.store_id.toString();
                    }
                    
                    if (!this.activeStoreId) {
                        this.success = false;
                        this.message = 'Please select a store first for redemption.';
                        this.resumeScanner();
                        return;
                    }
                    
                    // Fetch reward balance to show quantity selector if needed
                    this.fetchRedeemInfo(this.pendingToken).then((data) => {
                        // Check if verification is required
                        if (data.verification_required) {
                            // Show verification state inside the single action sheet
                            this.verificationData = {
                                customer_name: data.customer_name,
                                customer_email: data.customer_email,
                                public_token: data.public_token,
                                loyalty_account_id: data.loyalty_account_id,
                            };
                            this.verificationRequired = true;
                            this.showModal = true;
                        } else {
                            // Show quantity selector modal
                            this.verificationRequired = false;
                            this.showModal = true;
                        }
                    }).catch(() => {
                        // If fetch fails, still show modal with default values
                        this.verificationRequired = false;
                        this.showModal = true;
                    });
                },
                
                chooseStamp() {
                    if (!this.pendingToken) {
                        this.success = false;
                        this.message = 'Scan a customer card first.';
                        return;
                    }

                    // User chose to stamp instead
                    this.isRedeem = false;
                    this.verificationRequired = false;
                    
                    // Use public_token for stamping (even if they scanned a redeem QR)
                    let stampToken = null;
                    if (this.previewData.is_redeem_qr && this.previewData.public_token) {
                        // They scanned a redeem QR but want to stamp - use public token
                        stampToken = 'LA:' + this.previewData.public_token;
                    } else {
                        // Use original token (should be a stamp QR)
                        stampToken = this.pendingToken;
                    }
                    
                    this.showStampModal(stampToken);
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
                            this.redeemQuantity = Math.min(this.rewardBalance, Math.max(1, this.lastRedeemQuantity || 1));
                            
                            // Return data for verification check
                            return data;
                        } else {
                            // Fallback to 1 if fetch fails
                            this.rewardBalance = 1;
                            this.redeemQuantity = 1;
                            // Close modal on error and resume scanning
                            this.showModal = false;
                            this.resumeScanner();
                            throw new Error(data.message || 'Failed to fetch redeem info');
                        }
                    } catch (error) {
                        console.error('Error fetching redeem info:', error);
                        // Fallback to 1 if fetch fails
                        this.rewardBalance = 1;
                        this.redeemQuantity = 1;
                        // Close modal on error and resume scanning
                        this.showModal = false;
                        this.resumeScanner();
                        throw error;
                    }
                },
                
                async sendVerificationEmail() {
                    if (!this.verificationData || !this.verificationData.public_token) {
                        this.success = false;
                        this.message = 'Unable to send verification email. Please try again.';
                        return;
                    }
                    
                    this.sendingVerification = true;
                    
                    try {
                        const response = await fetch(`/c/${this.verificationData.public_token}/verify-email/send`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                        
                        const data = await response.json();
                        
                        if (response.ok) {
                            this.success = true;
                            this.message = 'Verification email sent successfully! The customer will receive an email to verify their address.';
                            this.showModal = false;
                            this.verificationRequired = false;
                            this.verificationData = null;
                            // Resume scanner after a short delay
                            setTimeout(() => {
                                this.resumeScanner();
                            }, 2000);
                        } else {
                            this.success = false;
                            this.message = data.message || 'Failed to send verification email. Please try again.';
                        }
                    } catch (error) {
                        console.error('Error sending verification email:', error);
                        this.success = false;
                        this.message = 'Network error. Please try again.';
                        this.setFailureContext(
                            'network',
                            'Could not send verification email',
                            'Check your connection and retry.'
                        );
                    } finally {
                        this.sendingVerification = false;
                    }
                },
                
                showStampModal(token) {
                    if (!token) return;
                    this.pendingToken = token;
                    this.stampCount = Math.max(1, this.lastStampCount || 1);
                    this.isRedeem = false;
                    this.verificationRequired = false;
                    this.showModal = true;
                },

                                cancelActionModal() {
                                    this.showModal = false;
                                    this.showModeToggle = false;
                                    this.verificationRequired = false;
                                    this.verificationData = null;
                                    this.previewData = null;
                                    // Resume scanning quickly so the merchant can scan again
                                    setTimeout(() => this.resumeScanner(), 200);
                                },

                confirmAction() {
                    this.showModal = false;
                    if (this.isRedeem) {
                        this.lastRedeemQuantity = Math.max(1, this.redeemQuantity);
                        this.lastAction = { mode: 'redeem', token: this.pendingToken, quantity: this.redeemQuantity };
                        this.redeem(this.pendingToken, this.redeemQuantity);
                    } else {
                        this.lastStampCount = Math.max(1, this.stampCount);
                        this.lastAction = { mode: 'stamp', token: this.pendingToken, quantity: this.stampCount };
                        this.stamp(this.pendingToken, this.stampCount);
                    }
                    
                    // Reset redeem quantity for next time
                    this.redeemQuantity = 1;
                    this.showModeToggle = false;
                    this.verificationRequired = false;
                    this.verificationData = null;
                    this.previewData = null;
                    
                    // Note: Scanner will resume automatically after cooldown via startCooldown()
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
                            this.clearFailureContext();
                            this.resultData = { 
                                customerLabel: data.customerLabel,
                                remaining_rewards: data.receipt?.remaining_rewards || 0
                            };
                            
                            // Show remaining rewards if any
                            if (data.receipt && data.receipt.remaining_rewards > 0) {
                                this.message += ` (${data.receipt.remaining_rewards} reward(s) remaining)`;
                            }
                            
                            // Start cooldown after successful redemption
                            this.startCooldown(data.cooldown_seconds ?? 5);
                        } else {
                            this.success = false;
                            // Use improved error messages from server
                            this.message = data.message || data.errors?.token?.[0] || data.errors?.quantity?.[0] || 'Redemption failed. Please try again.';
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.success = false;
                        this.message = error.message || 'Network error or server issue.';
                        this.setFailureContext(
                            'network',
                            'Could not complete redemption',
                            'Check your connection and retry.'
                        );
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
                            this.clearFailureContext();
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
                            
                            // Start cooldown after successful stamp (server sends cooldown_seconds, default 5)
                            this.startCooldown(data.cooldown_seconds ?? 5);
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
                        this.setFailureContext(
                            'network',
                            'Could not complete stamp action',
                            'Check your connection and retry.'
                        );
                    }
                },

                confirmCooldownOverride() {
                    this.showCooldownModal = false;
                    if (this.pendingCooldownToken) {
                        this.stamp(this.pendingCooldownToken, this.pendingCooldownCount, true);
                        this.pendingCooldownToken = null;
                        this.pendingCooldownCount = 1;
                    }
                },

                repeatLastAction() {
                    if (!this.lastAction || !this.lastAction.token) return;

                    if (this.lastAction.mode === 'redeem') {
                        this.isRedeem = true;
                        this.redeem(this.lastAction.token, this.lastAction.quantity || 1);
                    } else {
                        this.isRedeem = false;
                        this.stamp(this.lastAction.token, this.lastAction.quantity || 1);
                    }
                },

                clearResultAndResume() {
                    this.message = '';
                    this.success = false;
                    this.resultData = null;
                    this.resumeScanner();
                }
            }));
        });
    </script>
    @endpush
</x-merchant-layout>
