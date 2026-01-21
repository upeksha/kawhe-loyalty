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

                            <!-- Scanner Controls -->
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs text-gray-600 dark:text-gray-300" x-text="cameraStatus"></p>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        x-show="!isScanning"
                                        @click="startScanner()"
                                        class="px-3 py-2 text-xs font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition"
                                    >
                                        Start Camera
                                    </button>
                                    <button
                                        type="button"
                                        @click="switchCamera()"
                                        :disabled="!canSwitchCamera || !isScanning"
                                        class="px-3 py-2 text-xs font-medium rounded-lg border transition disabled:opacity-50 disabled:cursor-not-allowed bg-white hover:bg-gray-50 text-gray-800 border-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-white dark:border-gray-600"
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
                                    class="absolute inset-0 flex items-center justify-center bg-gray-900 bg-opacity-90 z-40 rounded-lg"
                                >
                                    <button
                                        type="button"
                                        @click="startScanner()"
                                        class="px-6 py-3 text-base font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition shadow-lg"
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
                                    class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 underline"
                                >
                                    Having trouble? Upload an image of the QR code
                                </button>
                                <div x-show="showUploadFallback" x-cloak class="mt-3 flex items-center gap-2">
                                    <input
                                        type="file"
                                        accept="image/*"
                                        @change="scanFromImageFile($event)"
                                        class="block w-full text-xs text-gray-700 bg-gray-50 border border-gray-300 rounded-lg cursor-pointer dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600"
                                    />
                                </div>
                            </div>

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
                                        <button @click="cancelActionModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">Cancel</button>
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
        
        /* Ensure video element is visible on iOS Safari */
        #reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block !important;
            background: #000 !important;
        }
        
        #reader canvas {
            display: block !important;
        }
        
        /* Ensure reader container is visible */
        #reader {
            position: relative !important;
            width: 100% !important;
            min-height: 300px !important;
            background: #000 !important;
            overflow: hidden !important;
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
                                    
                                    this.cameraStatus = 'Requesting camera permission…';
                                    this.isScanning = true;

                                    try {
                                        if (!this.html5QrCode) {
                                            this.html5QrCode = new Html5Qrcode('reader');
                                        }

                                        // For iOS Safari, we need to enumerate cameras first to get deviceId
                                        // facingMode doesn't work reliably on iOS Safari
                                        const isIOSSafari = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                                        
                                        if (isIOSSafari) {
                                            // iOS Safari: Enumerate cameras first, then use deviceId
                                            try {
                                                await this.loadCameras();
                                                
                                                if (this.cameras.length === 0) {
                                                    throw new Error('No cameras found');
                                                }
                                                
                                                // Try stored camera first
                                                const storedCameraId = localStorage.getItem('kawhe_scanner_camera_id');
                                                if (storedCameraId && this.cameras.find(c => c.id === storedCameraId)) {
                                                    await this.startWithCameraId(storedCameraId);
                                                    return;
                                                }
                                                
                                                // Pick preferred camera (back camera)
                                                const preferred = this.pickPreferredCameraId(this.cameras);
                                                if (preferred) {
                                                    await this.startWithCameraId(preferred);
                                                } else {
                                                    // Fallback to first available camera
                                                    await this.startWithCameraId(this.cameras[0].id);
                                                }
                                            } catch (e) {
                                                console.error('iOS Safari camera start failed:', e);
                                                // Fallback: try facingMode as last resort
                                                try {
                                                    await this.startWithFacingMode('environment');
                                                } catch (e2) {
                                                    throw e; // Throw original error
                                                }
                                            }
                                        } else {
                                            // Non-iOS: Try stored camera first
                                            const storedCameraId = localStorage.getItem('kawhe_scanner_camera_id');
                                            if (storedCameraId) {
                                                try {
                                                    await this.startWithCameraId(storedCameraId);
                                                    return;
                                                } catch (e) {
                                                    console.warn('Stored camera not available, falling back:', e);
                                                }
                                            }

                                            // Start with facingMode to get permission quickly
                                            await this.startWithFacingMode('environment');

                                            // After permission, enumerate and switch to deviceId
                                            await this.loadCameras();
                                            const preferred = this.pickPreferredCameraId(this.cameras);
                                            if (preferred) {
                                                await this.restartWithCameraId(preferred);
                                            }
                                        }
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

                                async startWithFacingMode(mode) {
                                    // Ensure scanner is stopped before starting
                                    try {
                                        await this.stopScanner();
                                    } catch (e) {
                                        // Ignore - might not be running
                                    }
                                    
                                    const config = { 
                                        fps: 10, 
                                        qrbox: { width: 250, height: 250 },
                                        aspectRatio: 1.0
                                    };
                                    const constraints = { facingMode: mode };
                                    try {
                                        await this.html5QrCode.start(
                                            constraints,
                                            config,
                                            (decodedText) => this.onScanSuccess(decodedText),
                                            (errorMessage) => {
                                                // Silently ignore "no QR code found" errors - these are expected
                                                // Only log actual errors (not parse errors)
                                                if (errorMessage && !errorMessage.includes('No MultiFormat Readers') && !errorMessage.includes('QR code parse error')) {
                                                    console.warn('Unexpected scan error:', errorMessage);
                                                }
                                            }
                                        );
                                        this.cameraStatus = 'Scanning…';
                                        this.isScanning = true;
                                    } catch (e) {
                                        console.error('Failed to start camera with facingMode:', e);
                                        throw e;
                                    }
                                },

                                async startWithCameraId(cameraId) {
                                    // Ensure scanner is stopped before starting
                                    try {
                                        await this.stopScanner();
                                    } catch (e) {
                                        // Ignore - might not be running
                                    }
                                    
                                    const isIOSSafari = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                                    
                                    // iOS Safari needs different configuration
                                    let config;
                                    if (isIOSSafari) {
                                        // For iOS Safari, use viewfinder dimensions instead of qrbox
                                        // This ensures the video stream is visible
                                        const readerElement = document.getElementById('reader');
                                        const containerWidth = readerElement.offsetWidth || 300;
                                        const containerHeight = readerElement.offsetHeight || 300;
                                        
                                        config = { 
                                            fps: 10,
                                            // Use viewfinder for iOS Safari - ensures video is visible
                                            viewfinderWidth: containerWidth,
                                            viewfinderHeight: containerHeight,
                                            // Smaller qrbox for better scanning
                                            qrbox: function(viewfinderWidth, viewfinderHeight) {
                                                const minEdgePercentage = 0.7; // Use 70% of the smaller edge
                                                const minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
                                                const qrboxSize = Math.floor(minEdgeSize * minEdgePercentage);
                                                return {
                                                    width: qrboxSize,
                                                    height: qrboxSize
                                                };
                                            },
                                            aspectRatio: 1.0,
                                            supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA],
                                            // iOS Safari specific: disable experimental features
                                            experimentalFeatures: {
                                                useBarCodeDetectorIfSupported: false
                                            }
                                        };
                                    } else {
                                        config = { 
                                            fps: 10, 
                                            qrbox: { width: 250, height: 250 },
                                            aspectRatio: 1.0,
                                            supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
                                        };
                                    }
                                    
                                    // For iOS Safari, use simpler constraints
                                    const videoConstraints = isIOSSafari 
                                        ? { deviceId: { exact: cameraId } }
                                        : { deviceId: { exact: cameraId }, facingMode: 'environment' };
                                    
                                    try {
                                        await this.html5QrCode.start(
                                            videoConstraints,
                                            config,
                                            (decodedText) => this.onScanSuccess(decodedText),
                                            (errorMessage) => {
                                                // Silently ignore "no QR code found" errors - these are expected
                                                // Only log actual errors (not parse errors)
                                                if (errorMessage && !errorMessage.includes('No MultiFormat Readers') && !errorMessage.includes('QR code parse error')) {
                                                    console.warn('Unexpected scan error:', errorMessage);
                                                }
                                            }
                                        );
                                        this.activeCameraId = cameraId;
                                        localStorage.setItem('kawhe_scanner_camera_id', cameraId);
                                        await this.loadCameras();
                                        this.cameraStatus = 'Scanning…';
                                        this.isScanning = true;
                                        
                                        // Force video element to be visible on iOS Safari
                                        if (isIOSSafari) {
                                            setTimeout(() => {
                                                const videoElement = document.querySelector('#reader video');
                                                if (videoElement) {
                                                    videoElement.style.width = '100%';
                                                    videoElement.style.height = '100%';
                                                    videoElement.style.objectFit = 'cover';
                                                    videoElement.style.display = 'block';
                                                }
                                            }, 100);
                                        }
                                    } catch (e) {
                                        console.error('Failed to start camera with deviceId:', e);
                                        throw e;
                                    }
                                },

                                async restartWithCameraId(cameraId) {
                                    try {
                                        await this.stopScanner();
                                    } catch (e) {
                                        // ignore
                                    }
                                    await this.startWithCameraId(cameraId);
                                },

                                async stopScanner() {
                                    if (!this.html5QrCode) {
                                        this.isScanning = false;
                                        return;
                                    }
                                    
                                    // stop() throws if not running; guard with try
                                    try {
                                        // Check if scanner is actually scanning before stopping
                                        const isScanning = await this.html5QrCode.getState();
                                        if (isScanning === Html5QrcodeScannerState.SCANNING) {
                                            await this.html5QrCode.stop();
                                        }
                                        this.isScanning = false;
                                    } catch (e) {
                                        // Ignore errors - scanner might not be running
                                        // Try to clear anyway
                                        try {
                                            await this.html5QrCode.clear();
                                        } catch (clearError) {
                                            // Ignore clear errors too
                                        }
                                        this.isScanning = false;
                                    }
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

                                async switchCamera() {
                                    if (!this.canSwitchCamera) return;
                                    const ids = this.cameras.map(c => c.id);
                                    const currentIdx = this.activeCameraId ? ids.indexOf(this.activeCameraId) : -1;
                                    const nextIdx = (currentIdx >= 0 ? currentIdx + 1 : 1) % ids.length;
                                    const nextId = ids[nextIdx];
                                    this.cameraStatus = 'Switching camera…';
                                    try {
                                        await this.restartWithCameraId(nextId);
                                    } catch (e) {
                                        console.error('Failed to switch camera:', e);
                                        this.cameraStatus = 'Could not switch camera.';
                                    }
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
</x-app-layout>

