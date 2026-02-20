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
                showChoiceModal: false, // New modal for choosing redeem vs stamp
                showVerificationModal: false, // Modal for verification required
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
                            this.resumeScanner();
                            return;
                        }
                        
                        // Store preview data
                        this.previewData = previewResult;
                        
                        // If customer has rewards available, show choice modal
                        if (previewResult.has_rewards && previewResult.reward_balance > 0) {
