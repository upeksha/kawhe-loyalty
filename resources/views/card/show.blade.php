<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $account->store->name }} - My Card</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-100 dark:bg-gray-900">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0" x-data="cardApp()" x-init="init()">
            <div class="w-full max-w-md p-6">
                <!-- Store Branding -->
                <div class="text-center mb-8">
                    <h1 id="store-name" class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ $account->store->name }}</h1>
                    <p id="reward-title" class="text-gray-600 dark:text-gray-400">{{ $account->store->reward_title }}</p>
                </div>

                <!-- Reward Available Card (dynamically shown/hidden) -->
                <div id="reward-available-card" class="bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-xl shadow-lg overflow-hidden mb-8 transform scale-105 border-4 border-yellow-200" 
                     style="display: {{ $account->reward_available_at && !$account->reward_redeemed_at ? 'block' : 'none' }};">
                    <div class="p-6 text-white text-center">
                        <h2 class="text-2xl font-extrabold mb-2">🎉 Reward Unlocked!</h2>
                        <p class="mb-4">You've earned a <span id="reward-title-available">{{ $account->store->reward_title }}</span></p>
                        
                        <div class="flex justify-center mb-4 p-4 bg-white rounded-lg inline-block mx-auto">
                            <div id="redeem-qr-container">
                                @if($account->redeem_token)
                                    {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate('REDEEM:' . $account->redeem_token) !!}
                                @endif
                            </div>
                        </div>
                        <p class="text-sm font-bold">Show this to merchant to redeem</p>
                    </div>
                </div>

                <!-- Recently Redeemed (dynamically shown/hidden) -->
                <div id="reward-redeemed-card" class="bg-green-100 dark:bg-green-900 rounded-xl shadow-lg overflow-hidden mb-8 p-6 text-center border border-green-300 dark:border-green-700"
                     style="display: {{ $account->reward_redeemed_at ? 'block' : 'none' }};">
                    <svg class="w-16 h-16 mx-auto text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <h2 class="text-xl font-bold text-green-800 dark:text-green-200 mb-2">Reward Redeemed!</h2>
                    <p class="text-green-700 dark:text-green-300">Enjoy your <span id="reward-title-redeemed">{{ $account->store->reward_title }}</span></p>
                    <p id="redeemed-date" class="text-xs text-green-600 dark:text-green-400 mt-2">
                        @if($account->reward_redeemed_at)
                            Redeemed on {{ $account->reward_redeemed_at->format('M d, Y H:i') }}
                        @endif
                    </p>
                </div>

                <!-- Digital Card (Standard) -->
                <div id="digital-card" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden mb-8 {{ $account->reward_available_at && !$account->reward_redeemed_at ? 'opacity-75 grayscale' : '' }}">
                    <div class="p-6 bg-gradient-to-r from-blue-600 to-blue-800 text-white text-center">
                        <div class="flex justify-center mb-4 p-4 bg-white rounded-lg inline-block mx-auto">
                            <div id="stamp-qr-container">
                                {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate('LA:' . $account->public_token) !!}
                            </div>
                        </div>
                        <p id="customer-name" class="text-sm opacity-90 font-mono">{{ $account->customer->name ?? 'Valued Customer' }}</p>
                        <p class="text-xs opacity-75 mt-1">Show this QR code to get stamped</p>
                    </div>

                    <!-- Progress -->
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Progress</span>
                            <span id="stamp-count" class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ $account->stamp_count }} / {{ $account->store->reward_target }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4 dark:bg-gray-700 mb-6">
                            @php
                                $percentage = min(100, ($account->stamp_count / $account->store->reward_target) * 100);
                            @endphp
                            <div id="progress-bar" class="bg-blue-600 h-4 rounded-full transition-all duration-500" style="width: {{ $percentage }}%"></div>
                        </div>

                        <div id="stamp-grid" class="grid grid-cols-5 gap-2">
                            @for ($i = 1; $i <= $account->store->reward_target; $i++)
                                <div class="stamp-item aspect-square rounded-full flex items-center justify-center text-sm font-bold
                                    {{ $i <= $account->stamp_count
                                        ? 'bg-blue-100 text-blue-800 border-2 border-blue-500'
                                        : 'bg-gray-100 text-gray-400 border-2 border-gray-200 dark:bg-gray-700 dark:border-gray-600' }}">
                                    {{ $i }}
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>

                <!-- Connection Status -->
                <div id="connection-status" class="bg-gray-50 dark:bg-gray-800 rounded-lg p-2 text-center mb-4">
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        <span id="status-indicator" class="inline-block w-2 h-2 rounded-full bg-gray-400 mr-2"></span>
                        <span id="status-text">Connecting...</span>
                    </p>
                </div>

                <!-- Add to Home Screen Hint -->
                <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-4 text-center">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        <svg class="w-5 h-5 inline-block mr-1 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <strong>Tip:</strong> Add this page to your Home Screen to access your card easily!
                    </p>
                </div>
            </div>
        </div>

        <script>
            // Register cardApp before Alpine initializes
            document.addEventListener('alpine:init', () => {
                Alpine.data('cardApp', () => ({
                    publicToken: '{{ $account->public_token }}',
                    accountData: null,

                    init() {
                        this.initialize();
                    },

                    async initialize() {
                        // Wait for QRCode library to load (for future updates)
                        await this.waitForQRCode();
                        
                        // Update connection status
                        this.updateConnectionStatus('connecting', 'Connecting...');
                        
                        // Set up WebSocket listener
                        if (window.Echo) {
                            const channelName = 'loyalty-card.' + this.publicToken;
                            console.log('Connecting to channel:', channelName);
                            console.log('Echo config:', {
                                host: window.Echo.connector?.options?.wsHost,
                                port: window.Echo.connector?.options?.wsPort || window.Echo.connector?.options?.wssPort,
                                scheme: window.Echo.connector?.options?.forceTLS ? 'wss' : 'ws'
                            });
                            
                            try {
                                // Monitor connection state
                                const checkConnection = () => {
                                    const socket = window.Echo.connector?.socket;
                                    if (socket) {
                                        const state = socket.readyState;
                                        if (state === 1) { // OPEN
                                            this.updateConnectionStatus('connected', 'Live updates active');
                                        } else if (state === 0) { // CONNECTING
                                            this.updateConnectionStatus('connecting', 'Connecting...');
                                        } else { // CLOSED or CLOSING
                                            this.updateConnectionStatus('disconnected', 'Disconnected - refresh page');
                                        }
                                    } else {
                                        this.updateConnectionStatus('disconnected', 'WebSocket not available');
                                    }
                                };
                                
                                // Check connection immediately and periodically
                                checkConnection();
                                setInterval(checkConnection, 2000);
                                
                                const channel = window.Echo.channel(channelName);
                                
                                channel
                                    .listen('.StampUpdated', (e) => {
                                        console.log('Stamp Updated event received:', e);
                                        // Use event data directly for immediate update
                                        if (e && e.stamp_count !== undefined) {
                                            // Show a brief visual feedback
                                            this.showUpdateNotification();
                                            this.updateUI(e);
                                            this.updateConnectionStatus('connected', 'Live updates active');
                                        } else {
                                            // Fallback to API call if event data is incomplete
                                            console.warn('Event data incomplete, falling back to API call');
                                            this.refreshCard();
                                        }
                                    })
                                    .error((error) => {
                                        console.error('Echo channel error:', error);
                                        this.updateConnectionStatus('error', 'Connection error');
                                    });
                                
                                console.log('Echo channel subscribed to:', channelName);
                                
                                // Test connection after a short delay
                                setTimeout(() => {
                                    checkConnection();
                                    console.log('Echo connection check:', {
                                        connected: window.Echo.connector?.socket?.readyState === 1,
                                        state: window.Echo.connector?.socket?.readyState,
                                        url: window.Echo.connector?.socket?.url
                                    });
                                }, 1000);
                            } catch (error) {
                                console.error('Error setting up Echo channel:', error);
                                this.updateConnectionStatus('error', 'Setup error: ' + error.message);
                            }
                        } else {
                            console.warn('Laravel Echo is not loaded. Real-time updates will not work.');
                            this.updateConnectionStatus('error', 'Echo not loaded');
                        }
                    },

                    updateConnectionStatus(status, text) {
                        const indicator = document.getElementById('status-indicator');
                        const statusText = document.getElementById('status-text');
                        
                        if (indicator && statusText) {
                            statusText.textContent = text;
                            
                            // Update indicator color
                            indicator.className = 'inline-block w-2 h-2 rounded-full mr-2';
                            if (status === 'connected') {
                                indicator.classList.add('bg-green-500');
                            } else if (status === 'connecting') {
                                indicator.classList.add('bg-yellow-500');
                            } else {
                                indicator.classList.add('bg-red-500');
                            }
                        }
                    },

                    async waitForQRCode() {
                        return new Promise((resolve) => {
                            if (typeof QRCode !== 'undefined') {
                                resolve();
                                return;
                            }
                            
                            const checkInterval = setInterval(() => {
                                if (typeof QRCode !== 'undefined') {
                                    clearInterval(checkInterval);
                                    resolve();
                                }
                            }, 100);
                            
                            // Timeout after 5 seconds
                            setTimeout(() => {
                                clearInterval(checkInterval);
                                resolve();
                            }, 5000);
                        });
                    },

                    async refreshCard() {
                        try {
                            const response = await fetch(`/api/card/${this.publicToken}`);
                            if (!response.ok) throw new Error('Failed to fetch card data');
                            
                            const data = await response.json();
                            this.accountData = data;
                            this.updateUI(data);
                        } catch (error) {
                            console.error('Error refreshing card:', error);
                        }
                    },

                    async updateUI(data) {
                        // Update stamp count
                        document.getElementById('stamp-count').textContent = `${data.stamp_count} / ${data.reward_target}`;

                        // Update progress bar
                        const percentage = Math.min(100, (data.stamp_count / data.reward_target) * 100);
                        document.getElementById('progress-bar').style.width = percentage + '%';

                        // Update stamp grid
                        const stampGrid = document.getElementById('stamp-grid');
                        const stampItems = stampGrid.querySelectorAll('.stamp-item');
                        stampItems.forEach((item, index) => {
                            const stampNumber = index + 1;
                            if (stampNumber <= data.stamp_count) {
                                item.className = 'stamp-item aspect-square rounded-full flex items-center justify-center text-sm font-bold bg-blue-100 text-blue-800 border-2 border-blue-500';
                            } else {
                                item.className = 'stamp-item aspect-square rounded-full flex items-center justify-center text-sm font-bold bg-gray-100 text-gray-400 border-2 border-gray-200 dark:bg-gray-700 dark:border-gray-600';
                            }
                        });

                        // Update reward states
                        const rewardAvailable = data.reward_available_at && !data.reward_redeemed_at;
                        const rewardRedeemed = data.reward_redeemed_at;

                        // Show/hide reward available card
                        const rewardAvailableCard = document.getElementById('reward-available-card');
                        const rewardRedeemedCard = document.getElementById('reward-redeemed-card');
                        const digitalCard = document.getElementById('digital-card');

                        if (rewardAvailable) {
                            rewardAvailableCard.style.display = 'block';
                            rewardRedeemedCard.style.display = 'none';
                            digitalCard.classList.add('opacity-75', 'grayscale');
                            
                            // Redeem QR code is server-generated, no need to regenerate client-side
                        } else {
                            rewardAvailableCard.style.display = 'none';
                        }

                        if (rewardRedeemed) {
                            rewardRedeemedCard.style.display = 'block';
                            digitalCard.classList.remove('opacity-75', 'grayscale');
                            
                            // Update redeemed date
                            if (data.reward_redeemed_at) {
                                const date = new Date(data.reward_redeemed_at);
                                document.getElementById('redeemed-date').textContent = 
                                    'Redeemed on ' + date.toLocaleDateString('en-US', { 
                                        month: 'short', 
                                        day: 'numeric', 
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    });
                            }
                        } else {
                            rewardRedeemedCard.style.display = 'none';
                        }

                        // QR codes are server-generated and don't need client-side regeneration
                        // The server-side QR codes in the initial page load are sufficient
                    },

                    showUpdateNotification() {
                        // Create a temporary notification
                        const notification = document.createElement('div');
                        notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                        notification.textContent = '✓ Card Updated!';
                        document.body.appendChild(notification);
                        
                        // Remove after 2 seconds
                        setTimeout(() => {
                            notification.style.opacity = '0';
                            notification.style.transition = 'opacity 0.3s';
                            setTimeout(() => notification.remove(), 300);
                        }, 2000);
                    },

                    async generateQRCode(containerId, text) {
                        // For now, we'll just refresh the page content via API
                        // QR codes are generated server-side, so we don't need client-side generation
                        // This function is kept for future use but currently just logs
                        console.log('QR code update requested for:', containerId, text);
                        
                        // If we need to update QR codes dynamically in the future,
                        // we can fetch the updated QR code from the server or use the API
                    }
                }));
            });
        </script>
    </body>
</html>
