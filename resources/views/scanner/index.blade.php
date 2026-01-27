<x-merchant-layout>
    <x-slot name="header">
        {{ __('Scanner') }}
    </x-slot>

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

                    <!-- Scan Mode Selection -->
                    <div class="mb-6">
                        <label class="block mb-2 text-sm font-medium text-stone-700">Scan Mode</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="scanMode = 'stamp'"
                                :class="scanMode === 'stamp' 
                                    ? 'bg-brand-600 text-white border-2 border-brand-700 shadow-md' 
                                    : 'bg-white text-stone-700 border-2 border-stone-300 hover:bg-stone-50'"
                                class="px-4 py-3 rounded-lg font-medium transition-all flex items-center justify-center gap-2"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                <span>Stamping Mode</span>
                            </button>
                            <button
                                type="button"
                                @click="scanMode = 'redeem'"
                                :class="scanMode === 'redeem' 
                                    ? 'bg-accent-600 text-white border-2 border-accent-700 shadow-md' 
                                    : 'bg-white text-stone-700 border-2 border-stone-300 hover:bg-stone-50'"
                                class="px-4 py-3 rounded-lg font-medium transition-all flex items-center justify-center gap-2"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                                </svg>
                                <span>Redeeming Mode</span>
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-stone-500" x-text="scanMode === 'stamp' ? 'Scan QR codes starting with LA: to add stamps' : 'Scan QR codes starting with LR: to redeem rewards'"></p>
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
                        <!-- Mode Indicator Badge -->
                        <div 
                            x-show="isScanning"
                            class="absolute top-4 left-4 z-30 px-3 py-1.5 rounded-full text-xs font-bold shadow-lg"
                            :class="scanMode === 'stamp' ? 'bg-brand-600 text-white' : 'bg-accent-600 text-white'"
                        >
                            <span x-text="scanMode === 'stamp' ? 'STAMP MODE' : 'REDEEM MODE'"></span>
                        </div>
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
                        <label for="manual_token" class="block mb-2 text-sm font-medium text-stone-700">Or enter token manually</label>
                        <div class="flex gap-2">
                            <x-ui.input type="text" id="manual_token" x-model="manualToken" placeholder="e.g. LA:..." class="flex-1" />
                            <button @click="handleScan(manualToken)" type="button" class="px-4 py-2 text-sm font-medium rounded-lg bg-brand-600 hover:bg-brand-700 text-white transition focus:outline-none focus:ring-2 focus:ring-brand-500">
                                Scan
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

    @push('scripts')
    <style>
        [x-cloak] { display: none !important; }
        
        /* Reader container */
        #reader {
            position: relative !important;
            width: 100% !important;
            min-height: 300px !important;
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
                stampCount: 1,
                pendingToken: null,
                isRedeem: false,
                rewardBalance: 1, // Default to 1 for single reward
                redeemQuantity: 1, // Quantity to redeem (default 1)
                scanMode: 'stamp', // 'stamp' or 'redeem' - pre-scan mode selection
                storeSwitched: false,
                switchedStoreName: '',
                showCooldownModal: false,
                cooldownData: null,
                pendingCooldownToken: null,
                pendingCooldownCount: 1,
                cooldownActive: false,
                cooldownSeconds: 3,
                cooldownInterval: null,

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
                                    } catch (e) {
                                        console.error('Failed to start scanner:', e);
                                        this.isScanning = false;
                                        
                                        // Better error messages - safely check e.message
                                        const errorMessage = e?.message || e?.toString() || '';
                                        const errorName = e?.name || '';
                                        
                                        if (errorName === 'NotAllowedError' || errorMessage.includes('permission')) {
                                            this.cameraStatus = 'Camera permission denied. Please allow camera access in Safari settings.';
                                        } else if (errorName === 'NotFoundError' || errorMessage.includes('camera')) {
                                            this.cameraStatus = 'No camera found. Please check your device.';
                                        } else if (errorMessage.includes('scan is ongoing')) {
                                            // Scanner already running - try stopping and restarting
                                            this.cameraStatus = 'Restarting camera…';
                                            setTimeout(() => this.startScanner(), 500);
                                        } else {
                                            this.cameraStatus = 'Camera unavailable. Tap "Start Camera" to try again.';
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
                                    } catch (e) {
                                        console.error('Failed to switch camera:', e);
                                        this.cameraStatus = 'Could not switch camera.';
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

                                startCooldown(seconds = 3) {
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
                                            this.cooldownSeconds = 3;
                                            
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
                    
                    // Determine if this is a redeem or stamp QR based on prefix
                    const isRedeemQR = token.startsWith('LR:') || token.startsWith('REDEEM:');
                    const isStampQR = token.startsWith('LA:');
                    
                    // Check if QR code matches selected scan mode
                    if (this.scanMode === 'redeem' && !isRedeemQR) {
                        this.success = false;
                        this.message = '⚠️ Wrong QR code! This is a stamp QR code. Please switch to "Stamping Mode" or scan a redeem QR code.';
                        this.resumeScanner();
                        return;
                    }
                    
                    if (this.scanMode === 'stamp' && !isStampQR && !isRedeemQR) {
                        // If no prefix, assume it's a stamp token (backward compatibility)
                        this.isRedeem = false;
                        this.showStampModal(token);
                        return;
                    }
                    
                    if (this.scanMode === 'stamp' && isRedeemQR) {
                        this.success = false;
                        this.message = '⚠️ Wrong QR code! This is a redeem QR code. Please switch to "Redeeming Mode" or scan a stamp QR code.';
                        this.resumeScanner();
                        return;
                    }
                    
                    // Note: store_id is now optional - backend will auto-detect from token
                    // But we still require it for redeem operations
                    if (isRedeemQR) {
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
                                            // Close modal on error and resume scanning
                                            this.showModal = false;
                                            this.resumeScanner();
                        }
                    } catch (error) {
                        console.error('Error fetching redeem info:', error);
                        // Fallback to 1 if fetch fails
                        this.rewardBalance = 1;
                        this.redeemQuantity = 1;
                                        // Close modal on error and resume scanning
                                        this.showModal = false;
                                        this.resumeScanner();
                    }
                },

                showStampModal(token) {
                    if (!token) return;
                    this.pendingToken = token;
                    this.stampCount = 1;
                    this.showModal = true;
                },

                                cancelActionModal() {
                                    this.showModal = false;
                                    // Resume scanning quickly so the merchant can scan again
                                    setTimeout(() => this.resumeScanner(), 200);
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
                            this.resultData = { 
                                customerLabel: data.customerLabel,
                                remaining_rewards: data.receipt?.remaining_rewards || 0
                            };
                            
                            // Show remaining rewards if any
                            if (data.receipt && data.receipt.remaining_rewards > 0) {
                                this.message += ` (${data.receipt.remaining_rewards} reward(s) remaining)`;
                            }
                            
                            // Start cooldown after successful redemption
                            this.startCooldown(3);
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
                            
                            // Start cooldown after successful stamp
                            this.startCooldown(3);
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
</x-merchant-layout>

