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
        
        <style>
            @keyframes glow {
                0%, 100% { opacity: 0.3; }
                50% { opacity: 0.6; }
            }
            .qr-pattern {
                background-image: 
                    repeating-linear-gradient(0deg, rgba(255,255,255,0.05) 0px, rgba(255,255,255,0.05) 1px, transparent 1px, transparent 20px),
                    repeating-linear-gradient(90deg, rgba(255,255,255,0.05) 0px, rgba(255,255,255,0.05) 1px, transparent 1px, transparent 20px);
                position: relative;
            }
            .qr-glow {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 200px;
                height: 200px;
                background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
                animation: glow 3s ease-in-out infinite;
            }
        </style>
    </head>
    <body class="font-sans antialiased" style="background-color: {{ $account->store->background_color ?? '#1F2937' }}; min-height: 100vh;">
        <div class="min-h-screen pb-8" x-data="cardApp()" x-init="init()">
            <div class="w-full max-w-md mx-auto px-4 pt-6">
                <!-- Reward Unlocked Card (Top) -->
                @if($account->reward_available_at && !$account->reward_redeemed_at)
                    <div class="bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 rounded-2xl shadow-xl overflow-hidden mb-4">
                        <div class="p-6 text-white">
                            <div class="flex items-start gap-3 mb-4">
                                <div class="text-4xl">🎉</div>
                                <div class="flex-1">
                                    <h2 class="text-2xl font-bold mb-1">Reward Unlocked!</h2>
                                    <p class="text-sm opacity-90">You've earned a <span id="reward-title-available" class="font-semibold">{{ $account->store->reward_title }}</span></p>
                                </div>
                            </div>
                            
                            @if(!$account->customer->email_verified_at)
                                <div class="bg-white/20 backdrop-blur-sm rounded-xl p-4 mt-4">
                                    <div class="flex gap-2">
                                        <button @click="sendVerification()" :disabled="verifying" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg disabled:opacity-50 transition">
                                            <span x-text="verifying ? 'Sending...' : 'Verify Email'"></span>
                                        </button>
                                        <button @click="bannerDismissed = true" class="flex-1 bg-white/30 hover:bg-white/40 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition">
                                            Maybe Later
                                        </button>
                                    </div>
                                    <template x-if="verifyMessage">
                                        <p class="mt-2 text-xs font-semibold text-green-200" x-text="verifyMessage"></p>
                                    </template>
                                    @if($errors->has('email'))
                                        <p class="mt-2 text-xs font-semibold text-red-200">{{ $errors->first('email') }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Main Loyalty Card -->
                <div class="bg-gray-800 rounded-2xl shadow-2xl overflow-hidden mb-4 qr-pattern" style="position: relative;">
                    <!-- QR Glow Effect -->
                    <div class="qr-glow"></div>
                    
                    <div class="p-6 relative z-10">
                        <!-- Always Visible QR Code for Stamping -->
                        <div class="flex justify-center mb-6">
                            <div class="bg-white rounded-xl p-3 shadow-lg">
                                <div id="stamp-qr-container">
                                    {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate('LA:' . $account->public_token) !!}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Locked State Message (when reward available but not verified) -->
                        @if($account->reward_available_at && !$account->reward_redeemed_at && !$account->customer->email_verified_at)
                            <div class="flex flex-col items-center justify-center mb-4">
                                <svg class="w-12 h-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                <p class="text-gray-300 text-xs font-medium">Verify Email to Redeem Reward</p>
                            </div>
                        @endif

                        <!-- Customer Name -->
                        <p id="customer-name" class="text-white text-lg font-semibold text-center mb-2">{{ $account->customer->name ?? 'Valued Customer' }}</p>
                        
                        <!-- Reward Title (hidden when reward unlocked) -->
                        @if(!$account->reward_available_at || $account->reward_redeemed_at)
                            <p id="reward-title" class="text-gray-400 text-xs text-center mb-4">{{ $account->store->reward_title }} at {{ $account->store->reward_target }} stamps</p>
                        @endif

                        <!-- Progress Section -->
                        <div class="mb-6">
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-gray-300 text-sm font-medium">Progress</span>
                                <span id="stamp-count" class="text-white text-sm font-bold">{{ $account->stamp_count }} / {{ $account->store->reward_target }}</span>
                            </div>
                            <!-- Circular Checkmarks Row -->
                            <div class="flex gap-2 justify-center flex-wrap">
                                @for ($i = 1; $i <= $account->store->reward_target; $i++)
                                    <div class="stamp-circle w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold
                                        {{ $i <= $account->stamp_count
                                            ? 'bg-green-500 text-white'
                                            : 'bg-gray-700 text-gray-400 border-2 border-gray-600' }}">
                                        @if($i <= $account->stamp_count)
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        @else
                                            {{ $i }}
                                        @endif
                                    </div>
                                @endfor
                            </div>
                        </div>

                        <!-- Recent Activity Section -->
                        <div class="border-t border-gray-700 pt-4">
                            <h3 class="text-gray-300 text-sm font-semibold mb-3">Recent Activity</h3>
                            <div id="transaction-history" class="space-y-2">
                                <p class="text-sm text-gray-500 text-center py-2">Loading transaction history...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add to Home Screen Card -->
                <div class="bg-gray-800 rounded-2xl shadow-xl overflow-hidden mb-4">
                    <div class="p-6">
                        <div class="flex items-center gap-4 mb-3">
                            <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                            </div>
                            <p class="text-white font-semibold">Add to Home Screen</p>
                        </div>
                        <p class="text-gray-400 text-xs">
                            <span class="font-semibold text-gray-300">Tip:</span> Add this page to your Home Screen to access your card easily!
                        </p>
                    </div>
                </div>

                <!-- Forget Card Link -->
                <div class="text-center">
                    <button @click="forgetCard()" x-show="!cardForgotten" class="text-gray-400 hover:text-gray-300 text-sm underline transition">
                        Forget This Card
                    </button>
                    <p x-show="cardForgotten" class="text-green-400 text-sm font-medium">
                        ✓ Card removed from this device
                    </p>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('cardApp', () => ({
                    publicToken: '{{ $account->public_token }}',
                    accountData: null,
                    bannerDismissed: false,
                    verifying: false,
                    verifyMessage: '',
                    cardForgotten: false,

                    init() {
                        this.persistCard();
                        this.initialize();
                    },

                    persistCard() {
                        localStorage.setItem('kawhe_last_card_{{ $account->store_id }}', this.publicToken);
                        @if($account->customer->email)
                            localStorage.setItem('kawhe_last_email_{{ $account->store_id }}', '{{ $account->customer->email }}');
                        @endif
                    },

                    forgetCard() {
                        try {
                            localStorage.removeItem('kawhe_last_card_{{ $account->store_id }}');
                            localStorage.removeItem('kawhe_last_email_{{ $account->store_id }}');
                            this.cardForgotten = true;
                        } catch (e) {
                            console.error('Error removing card from localStorage:', e);
                        }
                    },

                    async sendVerification() {
                        if (this.verifying) return;
                        this.verifying = true;
                        this.verifyMessage = '';

                        try {
                            const response = await fetch('{{ route("customer.email.verification.send", ["public_token" => $account->public_token]) }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                    'Accept': 'application/json'
                                }
                            });

                            const data = await response.json().catch(() => ({}));
                            
                            if (response.ok) {
                                this.verifyMessage = data.message || 'Verification email sent! Please check your inbox.';
                                // Reload page after 2 seconds to show QR
                                setTimeout(() => {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                // Handle rate limit (429) with better message
                                if (response.status === 429) {
                                    const waitTime = data.message?.match(/(\d+)\s+more\s+second/i);
                                    if (waitTime) {
                                        this.verifyMessage = data.message || 'Please wait before requesting another verification email.';
                                    } else {
                                        this.verifyMessage = 'Too many requests. Please wait a few minutes before trying again.';
                                    }
                                } else {
                                    this.verifyMessage = data.message || data.errors?.email?.[0] || 'Error sending verification email.';
                                }
                            }
                        } catch (e) {
                            this.verifyMessage = 'Network error. Please try again.';
                        } finally {
                            this.verifying = false;
                        }
                    },

                    async initialize() {
                        this.loadTransactionHistory();
                        
                        if (window.Echo) {
                            const channelName = 'loyalty-card.' + this.publicToken;
                            
                            try {
                                const channel = window.Echo.channel(channelName);
                                
                                channel
                                    .listen('.StampUpdated', (e) => {
                                        console.log('Stamp Updated event received:', e);
                                        if (e && e.stamp_count !== undefined) {
                                            this.showUpdateNotification();
                                            this.updateUI(e);
                                            this.loadTransactionHistory();
                                        } else {
                                            this.refreshCardWithRetry();
                                        }
                                    })
                                    .error((error) => {
                                        console.error('Echo channel error:', error);
                                    });
                            } catch (error) {
                                console.error('Error setting up Echo channel:', error);
                            }
                        }
                    },

                    async loadTransactionHistory() {
                        try {
                            const response = await fetch(`/api/card/${this.publicToken}/transactions`);
                            if (!response.ok) throw new Error('Failed to fetch transactions');
                            
                            const data = await response.json();
                            this.renderTransactionHistory(data.transactions);
                        } catch (error) {
                            console.error('Error loading transaction history:', error);
                            const container = document.getElementById('transaction-history');
                            if (container) {
                                container.innerHTML = '<p class="text-sm text-gray-500 text-center">Unable to load transaction history</p>';
                            }
                        }
                    },

                    renderTransactionHistory(transactions) {
                        const container = document.getElementById('transaction-history');
                        if (!container) return;
                        
                        if (!transactions || transactions.length === 0) {
                            container.innerHTML = '<p class="text-sm text-gray-500 text-center">No recent activity</p>';
                            return;
                        }
                        
                        container.innerHTML = transactions.map(tx => `
                            <div class="flex items-center justify-between p-3 bg-gray-700/50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center bg-green-500/20 text-green-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-white text-sm font-medium">${tx.description}</p>
                                        <p class="text-gray-400 text-xs">${tx.formatted_date}</p>
                                    </div>
                                </div>
                                <span class="text-green-400 text-sm font-bold">
                                    +${tx.points}
                                </span>
                            </div>
                        `).join('');
                    },

                    async updateUI(data) {
                        // Update stamp count
                        const stampCountEl = document.getElementById('stamp-count');
                        if (stampCountEl) {
                            stampCountEl.textContent = `${data.stamp_count} / ${data.reward_target}`;
                        }

                        // Update progress circles
                        const circles = document.querySelectorAll('.stamp-circle');
                        circles.forEach((circle, index) => {
                            const stampNumber = index + 1;
                            if (stampNumber <= data.stamp_count) {
                                circle.className = 'stamp-circle w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold bg-green-500 text-white';
                                circle.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>';
                            } else {
                                circle.className = 'stamp-circle w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold bg-gray-700 text-gray-400 border-2 border-gray-600';
                                circle.textContent = stampNumber;
                            }
                        });

                        // Reload page if reward becomes available to show unlock card
                        if (data.reward_available_at && !data.reward_redeemed_at) {
                            window.location.reload();
                        }
                    },

                    async refreshCardWithRetry(maxRetries = 3) {
                        for (let attempt = 1; attempt <= maxRetries; attempt++) {
                            try {
                                const response = await fetch(`/api/card/${this.publicToken}`);
                                if (!response.ok) throw new Error('Failed to fetch card data');
                                
                                const data = await response.json();
                                this.accountData = data;
                                this.updateUI(data);
                                this.loadTransactionHistory();
                                return;
                            } catch (error) {
                                if (attempt === maxRetries) {
                                    console.error('Failed to refresh card after', maxRetries, 'attempts');
                                } else {
                                    await new Promise(resolve => setTimeout(resolve, 1000 * attempt));
                                }
                            }
                        }
                    },

                    showUpdateNotification() {
                        const notification = document.createElement('div');
                        notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 text-sm';
                        notification.textContent = '✓ Card Updated!';
                        document.body.appendChild(notification);
                        
                        setTimeout(() => {
                            notification.style.opacity = '0';
                            notification.style.transition = 'opacity 0.3s';
                            setTimeout(() => notification.remove(), 300);
                        }, 2000);
                    }
                }));
            });
        </script>
    </body>
</html>
