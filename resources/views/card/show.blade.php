@php
    $bg    = $account->store->background_color ?? '#1F2937';
    $brand = $account->store->brand_color ?? '#0EA5E9';
    $hex   = ltrim($bg, '#');
    if (strlen($hex) === 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $textOnBg  = $lum < 0.5 ? '#ffffff' : '#111827';
        $mutedOnBg = $lum < 0.5 ? 'rgba(255,255,255,0.65)' : 'rgba(17,24,39,0.6)';
        if ($lum < 0.5) {
            $surface = sprintf('#%02x%02x%02x', min(255,$r+28), min(255,$g+28), min(255,$b+28));
            $inner   = sprintf('#%02x%02x%02x', min(255,$r+18), min(255,$g+18), min(255,$b+18));
            $divider = 'rgba(255,255,255,0.09)';
        } else {
            $surface = sprintf('#%02x%02x%02x', max(0,$r-18), max(0,$g-18), max(0,$b-18));
            $inner   = sprintf('#%02x%02x%02x', max(0,$r-10), max(0,$g-10), max(0,$b-10));
            $divider = 'rgba(0,0,0,0.08)';
        }
    } else {
        $textOnBg  = '#ffffff';
        $mutedOnBg = 'rgba(255,255,255,0.65)';
        $surface   = '#374151';
        $inner     = '#2d3748';
        $divider   = 'rgba(255,255,255,0.09)';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="{{ $bg }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ $account->store->name }}">
        <link rel="manifest" href="{{ route('card.manifest', ['public_token' => $account->public_token]) }}">
        <link rel="apple-touch-icon" href="{{ asset('favicon.ico') }}">

        <title>{{ $account->store->name }} – My Card</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            [x-cloak] { display: none !important; }
            body { background-color: {{ $bg }}; color: {{ $textOnBg }}; }
            .card-surface  { background-color: {{ $surface }}; }
            .card-inner    { background-color: {{ $inner }}; }
            .card-text     { color: {{ $textOnBg }}; }
            .card-muted    { color: {{ $mutedOnBg }}; }
            .card-divider  { border-color: {{ $divider }}; }
            .card-btn      { background-color: {{ $brand }}; color: #fff; }
            .card-btn:hover { filter: brightness(1.1); }
            .card-btn-icon { background-color: {{ $brand }}; }
            .card-badge    { background-color: {{ $brand }}; color: #fff; }
            .card-qr-border { border-color: {{ $brand }}; }
            .card-accent   { color: {{ $brand }}; }
            .loyalty-card {
                background: linear-gradient(135deg, {{ $surface }} 0%, {{ $bg }} 50%, {{ $inner }} 100%);
                position: relative;
                overflow: hidden;
            }
            .loyalty-card::before {
                content: '';
                position: absolute;
                inset: 0;
                background:
                    radial-gradient(ellipse at 15% 20%, {{ $brand }}30 0%, transparent 55%),
                    radial-gradient(ellipse at 85% 80%, {{ $brand }}20 0%, transparent 50%);
                pointer-events: none;
            }
            @keyframes glow-pulse {
                0%, 100% { opacity: 0.5; transform: translate(-50%, -50%) scale(1); }
                50%       { opacity: 0.9; transform: translate(-50%, -50%) scale(1.15); }
            }
            .card-glow {
                position: absolute;
                top: 38%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 260px;
                height: 260px;
                background: radial-gradient(circle, {{ $brand }}28 0%, transparent 70%);
                animation: glow-pulse 4s ease-in-out infinite;
                pointer-events: none;
            }
        </style>
    </head>
    <body class="font-sans antialiased" style="min-height: 100vh; min-height: 100dvh;" x-data="cardApp()" x-init="init()">
        <div class="min-h-screen pb-8">
            <div class="w-full max-w-md mx-auto px-4 pt-6">
                <!-- Success Message (e.g., from email verification) -->
                @if(session('message'))
                    <div class="bg-green-500 text-white rounded-lg p-4 mb-4 shadow-lg">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <p class="font-semibold">{{ session('message') }}</p>
                        </div>
                    </div>
                @endif
                
                <!-- Error Messages -->
                @if($errors->any())
                    <div class="bg-red-500 text-white rounded-lg p-4 mb-4 shadow-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <p class="font-semibold">Error</p>
                        </div>
                        <ul class="list-disc list-inside text-sm">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                <!-- Reward Unlocked Card (Top) -->
                @if(($account->reward_balance ?? 0) > 0)
                    <div x-show="rewardBalance > 0" class="bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 rounded-2xl shadow-xl overflow-hidden mb-4">
                        <div class="p-6 text-white">
                            <div class="flex items-start gap-3 mb-4">
                                <div class="text-4xl">🎉</div>
                                <div class="flex-1">
                                    <h2 class="text-2xl font-bold mb-1">Reward{{ ($account->reward_balance ?? 0) > 1 ? 's' : '' }} Unlocked!</h2>
                                    <p class="text-sm opacity-90">
                                        <span id="reward-balance-banner" class="font-semibold text-lg">{{ $account->reward_balance ?? 0 }}</span> 
                                        <span id="reward-title-available" class="font-semibold">{{ $account->store->reward_title }}</span>
                                        @if(($account->reward_balance ?? 0) > 1)
                                            <span> available</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            
                            @if(!$account->verified_at && $account->customer->email)
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
                <div class="loyalty-card rounded-2xl shadow-2xl overflow-hidden mb-4" style="border: 3px solid {{ $brand }};">
                    <!-- Ambient Glow -->
                    <div class="card-glow"></div>
                    
                    <div class="p-6 relative">
                        <!-- Store Logo (if available) -->
                        @if($account->store->logo_path)
                            <div class="flex justify-center mb-4">
                                <img src="{{ $account->store->logo_url }}" alt="{{ $account->store->name }} logo" class="h-20 w-20 object-contain rounded-lg bg-white/10 backdrop-blur-sm p-2 border-2" style="border-color: {{ $brand }};">
                            </div>
                        @endif
                        
                        <!-- Always Visible QR Code for Stamping -->
                        <div class="flex flex-col items-center justify-center mb-6">
                            <!-- Mode Badge -->
                            <div class="card-badge mb-2 px-3 py-1 rounded-full text-xs font-bold shadow-md">
                                STAMP MODE
                            </div>
                            <div class="bg-white rounded-xl p-3 shadow-lg border-2 card-qr-border">
                                <div id="stamp-qr-container">
                                    {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->errorCorrection('L')->margin(1)->generate('LA:' . $account->public_token) !!}
                                </div>
                            </div>
                            <p class="mt-2 text-xs card-muted text-center">Scan to add stamps</p>
                            @if($account->manual_entry_code ?? null)
                                <p class="mt-1 text-sm font-mono font-bold card-text tracking-widest">{{ $account->manual_entry_code }}</p>
                                <p class="text-xs card-muted">If scan fails, tell staff this code</p>
                            @endif
                        </div>
                        
                        <!-- Redeem Reward Button (when reward available and not redeemed) -->
                        <div x-show="rewardBalance > 0 && hasRedeemToken" class="flex flex-col items-center justify-center mb-6">
                            <!-- Redeem Button -->
                            <button 
                                @click="showRedeemModal = true"
                                class="w-full bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 hover:from-yellow-500 hover:via-yellow-600 hover:to-yellow-700 text-white font-bold py-4 px-6 rounded-xl shadow-lg transition-all duration-200 transform hover:scale-105 flex items-center justify-center gap-2"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Redeem My Reward</span>
                                <span x-show="rewardBalance > 1" class="text-sm opacity-90" x-text="`(${rewardBalance} available)`"></span>
                            </button>
                        </div>

                        <!-- Customer Name -->
                        <p id="customer-name" class="card-text text-lg font-semibold text-center mb-2">{{ $account->customer->name ?? 'Valued Customer' }}</p>
                        
                        <!-- Reward Title (hidden when reward unlocked) -->
                        @if(($account->reward_balance ?? 0) == 0)
                            <p id="reward-title" class="card-muted text-xs text-center mb-4">{{ $account->store->reward_title }} at {{ $account->store->reward_target }} stamps</p>
                        @endif

                        <!-- Progress Section -->
                        <div class="mb-6">
                            <div class="flex justify-between items-center mb-3">
                                <span class="card-muted text-sm font-medium">Progress</span>
                                <span id="stamp-count" class="card-text text-sm font-bold">{{ $account->stamp_count }} / {{ $account->store->reward_target }}</span>
                            </div>
                            @if(($account->reward_balance ?? 0) > 0)
                                <p id="reward-balance-display" class="text-yellow-400 text-center text-sm font-semibold mb-2">
                                    Rewards Available: {{ $account->reward_balance }}
                                </p>
                            @endif
                            <!-- Circular Checkmarks Row -->
                            <div id="stamp-circles-container" class="flex gap-2 justify-center flex-wrap">
                                @for ($i = 1; $i <= $account->store->reward_target; $i++)
                                    @if($i <= $account->stamp_count)
                                        <div class="stamp-circle w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white"
                                            style="background-color: {{ $brand }};">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="stamp-circle w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold"
                                            style="background-color: {{ $inner }}; color: {{ $mutedOnBg }}; border: 2px solid {{ $mutedOnBg }}44;">
                                            {{ $i }}
                                        </div>
                                    @endif
                                @endfor
                            </div>
                        </div>

                        <!-- Recent Activity Section -->
                        <div class="border-t card-divider pt-4">
                            <h3 class="card-muted text-sm font-semibold mb-3">Recent Activity</h3>
                            <div id="transaction-history" class="space-y-2">
                                <p class="text-sm card-muted text-center py-2">Loading transaction history...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add to Wallet Cards -->
                <div class="space-y-4 mb-4">
                    <!-- Add to Apple Wallet Card (iOS only) -->
                    <div class="card-surface rounded-2xl shadow-xl overflow-hidden" x-show="isIOS" x-cloak>
                        <div class="p-6">
                            <div class="flex items-center gap-4 mb-3">
                                <div class="w-12 h-12 bg-black rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.05 20.28c-.98.95-2.05.88-3.08.82-.52-.03-1.04-.06-1.56-.1-1.98-.18-3.96-.36-5.85-1.1-1.23-.48-2.18-1.21-2.8-2.33-1.24-2.26-.6-4.67.95-6.68.93-1.2 2.1-2.1 3.5-2.7 1.4-.6 2.9-.9 4.4-.9 1.5 0 3 .3 4.4.9 1.4.6 2.6 1.5 3.5 2.7 1.55 2.01 2.19 4.42.95 6.68-.62 1.12-1.57 1.85-2.8 2.33-.3.12-.6.22-.9.3-.3.08-.6.15-.9.2-.3.05-.6.1-.9.13-.3.03-.6.05-.9.07-.3.02-.6.03-.9.03zm-1.05-8.28c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2zm-6 0c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2zm6 4c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2zm-6 0c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2z"/>
                                    </svg>
                                </div>
                                <p class="card-text font-semibold">Add to Apple Wallet</p>
                            </div>
                            <p class="card-muted text-xs mb-4">
                                <span class="font-semibold card-text">Tip:</span> Add your loyalty card to Apple Wallet for quick access!
                            </p>
                            <a 
                                href="{{ URL::signedRoute('wallet.apple.download', ['public_token' => $account->public_token]) }}"
                                class="block w-full flex justify-center hover:opacity-90 transition-opacity"
                                onclick="downloadAppleWalletPass(this.href); return false;">
                                <img 
                                    src="{{ asset('wallet-badges/add-to-apple-wallet.svg') }}" 
                                    alt="Add to Apple Wallet" 
                                    class="w-full h-auto mx-auto"
                                    style="max-width: 200px; height: auto;"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="inline-flex items-center justify-center gap-2 w-full bg-black hover:bg-gray-900 text-white font-semibold py-3 px-4 rounded-lg transition-colors" style="display: none;">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.05 20.28c-.98.95-2.05.88-3.08.82-.52-.03-1.04-.06-1.56-.1-1.98-.18-3.96-.36-5.85-1.1-1.23-.48-2.18-1.21-2.8-2.33-1.24-2.26-.6-4.67.95-6.68.93-1.2 2.1-2.1 3.5-2.7 1.4-.6 2.9-.9 4.4-.9 1.5 0 3 .3 4.4.9 1.4.6 2.6 1.5 3.5 2.7 1.55 2.01 2.19 4.42.95 6.68-.62 1.12-1.57 1.85-2.8 2.33-.3.12-.6.22-.9.3-.3.08-.6.15-.9.2-.3.05-.6.1-.9.13-.3.03-.6.05-.9.07-.3.02-.6.03-.9.03zm-1.05-8.28c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2zm-6 0c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2zm6 4c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2zm-6 0c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2z"/>
                                    </svg>
                                    <span>Add to Apple Wallet</span>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Add to Google Wallet Card (Android only) -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-200" x-show="isAndroid" x-cloak>
                        <div class="p-6">
                            <div class="flex items-center gap-4 mb-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                    </svg>
                                </div>
                                <p class="text-gray-900 font-semibold">Add to Google Wallet</p>
                            </div>
                            <p class="text-gray-600 text-xs mb-4">
                                <span class="font-semibold text-gray-700">Tip:</span> Add your loyalty card to Google Wallet for quick access!
                            </p>
                            <a 
                                href="{{ URL::signedRoute('wallet.google.save', ['public_token' => $account->public_token]) }}"
                                class="block w-full flex justify-center hover:opacity-90 transition-opacity"
                                aria-label="Add to Google Wallet">
                                <img 
                                    src="{{ asset('wallet-badges/add-to-google-wallet.svg') }}" 
                                    alt="Add to Google Wallet" 
                                    width="199" 
                                    height="55"
                                    class="mx-auto h-12 w-auto max-w-[199px]"
                                    loading="lazy">
                            </a>
                        </div>
                    </div>
                </div>

                    <!-- Add to Home Screen Card -->
                <div class="card-surface rounded-2xl shadow-xl overflow-hidden mb-4" x-show="showInstallPrompt" x-cloak>
                        <div class="p-6">
                            <div class="flex items-center gap-4 mb-3">
                                <div class="w-12 h-12 card-btn-icon rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                </div>
                                <p class="card-text font-semibold">Add to Home Screen</p>
                            </div>
                            <p class="card-muted text-xs mb-4">
                                <span class="font-semibold card-text">Tip:</span> Add this page to your Home Screen to access your card easily!
                            </p>
                            <button 
                                @click="installPWA()" 
                                x-show="deferredPrompt !== null"
                                class="card-btn w-full font-semibold py-2 px-4 rounded-lg transition-colors">
                                Install App
                            </button>
                            <div x-show="deferredPrompt === null && !isIOS && !isStandalone" class="card-muted text-xs space-y-1">
                                <p class="font-semibold card-text mb-1">Manual Instructions:</p>
                                <p><strong>Chrome/Edge:</strong> Click menu (⋮) → "Install app"</p>
                                <p><strong>Firefox:</strong> Click menu (☰) → "Install"</p>
                                <p><strong>Safari:</strong> Share button (□↑) → "Add to Home Screen"</p>
                            </div>
                            <div x-show="isIOS && !isStandalone" class="card-muted text-xs space-y-2">
                                <p class="font-semibold card-text">iOS Instructions:</p>
                                <ol class="list-decimal list-inside space-y-1">
                                    <li>Tap the Share button <svg class="inline w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"></path></svg> at the bottom</li>
                                    <li>Scroll down and tap "Add to Home Screen"</li>
                                    <li>Tap "Add" to confirm</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                <!-- Forget Card Link -->
                <div class="text-center">
                    <button @click="forgetCard()" x-show="!cardForgotten" class="card-muted hover:opacity-80 text-sm underline transition">
                        Forget This Card
                    </button>
                    <p x-show="cardForgotten" class="text-green-400 text-sm font-medium">
                        ✓ Card removed from this device
                    </p>
                </div>
            </div>
        </div>{{-- end min-h-screen wrapper --}}

        <!-- ============================================================ -->
        <!-- Wallet Nudge Modal — shown every time user arrives via email -->
            <!-- ============================================================ -->
            <div
                x-show="showWalletNudge"
                x-cloak
                @keydown.escape.window="showWalletNudge = false"
                class="fixed inset-0 z-[100] flex flex-col"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
            >
                <!-- Backdrop (only visible on sm+, on mobile the sheet fills the screen) -->
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm sm:block" @click="showWalletNudge = false"></div>

                <!-- Full-height sheet on mobile, centred card on sm+ -->
                <div
                    @click.stop
                    class="relative flex flex-col w-full h-full sm:h-auto sm:max-w-md sm:mx-auto sm:my-auto sm:rounded-3xl sm:max-h-[90vh] shadow-2xl overflow-hidden"
                    style="background: linear-gradient(160deg, {{ $surface }} 0%, {{ $bg }} 100%);"
                    x-transition:enter="transition ease-out duration-350"
                    x-transition:enter-start="opacity-0 translate-y-6"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-6"
                >
                    <!-- Brand accent bar -->
                    <div class="flex-shrink-0 h-1 w-full" style="background: linear-gradient(90deg, {{ $brand }}, {{ $brand }}88);"></div>

                    <!-- Close button -->
                    <button
                        @click="showWalletNudge = false"
                        class="absolute top-4 right-4 z-10 w-9 h-9 flex items-center justify-center rounded-full transition hover:opacity-80"
                        style="background: {{ $brand }}22;"
                        aria-label="Close"
                    >
                        <svg class="w-5 h-5 card-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    <!-- Scrollable content -->
                    <div class="flex-1 overflow-y-auto px-6 pt-6 pb-safe-bottom" style="padding-bottom: max(2rem, env(safe-area-inset-bottom));">
                        <!-- Drag handle (mobile feel) -->
                        <div class="w-10 h-1 rounded-full mx-auto mb-6 opacity-30" style="background: {{ $textOnBg }};"></div>

                        <!-- Icon -->
                        <div class="flex justify-center mb-5">
                            <div class="w-20 h-20 rounded-3xl flex items-center justify-center shadow-lg" style="background: {{ $brand }}22; border: 2px solid {{ $brand }}44;">
                                <svg class="w-10 h-10" style="color: {{ $brand }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Headline -->
                        <h2 class="text-2xl font-bold text-center card-text mb-2">Save your card to Wallet</h2>
                        <p class="text-center card-muted text-sm mb-6 leading-relaxed">
                            Stop searching for this link every visit.<br>
                            Your loyalty card lives in your Wallet — one tap away, always.
                        </p>

                        <!-- Benefit pills -->
                        <div class="space-y-3 mb-7">
                            @foreach([
                                ['icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'text' => 'Instant access — no link, no login'],
                                ['icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', 'text' => 'Get notified when you earn a reward'],
                                ['icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'text' => 'Your card is always secure & private'],
                            ] as $benefit)
                                <div class="flex items-center gap-3 px-4 py-3 rounded-2xl" style="background: {{ $brand }}15;">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-xl flex items-center justify-center" style="background: {{ $brand }}25;">
                                        <svg class="w-4 h-4" style="color: {{ $brand }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $benefit['icon'] }}"/>
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium card-text">{{ $benefit['text'] }}</span>
                                </div>
                            @endforeach
                        </div>

                        <!-- Wallet buttons -->
                        <div class="space-y-3">
                            <!-- Apple Wallet (iOS) -->
                            <div x-show="isIOS">
                                <a
                                    href="{{ URL::signedRoute('wallet.apple.download', ['public_token' => $account->public_token]) }}"
                                    onclick="downloadAppleWalletPass(this.href); return false;"
                                    @click="walletAdded()"
                                    class="flex items-center justify-center gap-3 w-full py-4 px-5 rounded-2xl font-semibold text-white text-base transition hover:opacity-90 shadow-lg"
                                    style="background: #000;"
                                >
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
                                    Add to Apple Wallet
                                </a>
                            </div>

                            <!-- Google Wallet (Android) -->
                            <div x-show="isAndroid">
                                <a
                                    href="{{ URL::signedRoute('wallet.google.save', ['public_token' => $account->public_token]) }}"
                                    @click="walletAdded()"
                                    class="flex items-center justify-center w-full hover:opacity-90 transition"
                                >
                                    <img src="{{ asset('wallet-badges/add-to-google-wallet.svg') }}" alt="Add to Google Wallet" class="h-14 w-auto">
                                </a>
                            </div>

                            <!-- Neither iOS nor Android — show both options -->
                            <div x-show="!isIOS && !isAndroid" class="space-y-3">
                                <a
                                    href="{{ URL::signedRoute('wallet.apple.download', ['public_token' => $account->public_token]) }}"
                                    onclick="downloadAppleWalletPass(this.href); return false;"
                                    @click="walletAdded()"
                                    class="flex items-center justify-center gap-3 w-full py-4 px-5 rounded-2xl font-semibold text-white text-base transition hover:opacity-90 shadow-lg"
                                    style="background: #000;"
                                >
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
                                    Add to Apple Wallet
                                </a>
                                <a
                                    href="{{ URL::signedRoute('wallet.google.save', ['public_token' => $account->public_token]) }}"
                                    @click="walletAdded()"
                                    class="flex items-center justify-center w-full hover:opacity-90 transition"
                                >
                                    <img src="{{ asset('wallet-badges/add-to-google-wallet.svg') }}" alt="Add to Google Wallet" class="h-14 w-auto">
                                </a>
                            </div>
                        </div>

                        <!-- Dismiss -->
                        <button
                            @click="showWalletNudge = false"
                            class="mt-5 mb-2 w-full text-center text-sm card-muted hover:opacity-80 transition py-3"
                        >
                            I'll keep using the browser link
                        </button>
                    </div>
                </div>
            </div>

            <!-- Redeem QR Code Modal (Full Screen Popup) -->
            @if(($account->reward_balance ?? 0) > 0 && $account->redeem_token)
                <div 
                    x-show="showRedeemModal && rewardBalance > 0 && hasRedeemToken" 
                    x-cloak
                    @click.away="showRedeemModal = false"
                    @keydown.escape.window="showRedeemModal = false"
                    class="fixed inset-0 z-[80] flex items-center justify-center bg-black bg-opacity-75 backdrop-blur-sm"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                >
                    <div 
                        @click.stop
                        class="card-surface rounded-3xl shadow-2xl p-8 max-w-sm w-full mx-4 relative"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 transform scale-100"
                        x-transition:leave-end="opacity-0 transform scale-95"
                    >
                        <!-- Close Button -->
                        <button 
                            @click="showRedeemModal = false"
                            class="absolute top-4 right-4 card-muted hover:opacity-80 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>

                        <!-- Modal Content -->
                        <div class="text-center">
                                <div class="mb-4">
                                    <!-- Redeem Mode Badge -->
                                    <div class="card-badge inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-bold shadow-lg mb-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                                        </svg>
                                        <span>REDEEM MODE</span>
                                    </div>
                                    <div class="text-4xl mb-2">🎁</div>
                                    <h2 class="text-2xl font-bold card-text mb-2">Redeem Your Reward!</h2>
                                    <p class="card-muted text-sm">Show this QR code to the merchant to redeem</p>
                                </div>

                                <!-- QR Code -->
                                <div class="bg-white rounded-xl p-6 shadow-lg border-4 card-qr-border mb-4 flex flex-col items-center">
                                    <div id="redeem-qr-container">
                                        @if($account->redeem_token)
                                            {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(250)->errorCorrection('L')->margin(1)->generate('LR:' . $account->redeem_token) !!}
                                        @else
                                            <p class="text-red-500">Error: Redeem token not available. Please refresh the page.</p>
                                        @endif
                                    </div>
                                    <p class="mt-3 text-xs font-semibold card-accent">Scan to redeem reward</p>
                                    @if($account->manual_entry_code ?? null)
                                        <p class="mt-2 text-sm font-mono font-bold card-accent tracking-widest">{{ $account->manual_entry_code }}</p>
                                        <p class="text-xs card-accent">If scan fails, tell staff this code</p>
                                    @endif
                                </div>

                                <p class="card-muted text-sm mb-4">
                                    Present this QR code to claim your <span class="font-semibold text-yellow-400">{{ $account->store->reward_title }}</span>
                                </p>
                                @if(($account->reward_balance ?? 0) > 1)
                                    <p class="text-yellow-400 text-xs text-center mb-2">
                                        Redeems 1 reward. Remaining after redeem: {{ ($account->reward_balance ?? 0) - 1 }}
                                    </p>
                                @endif

                                <button 
                                    @click="showRedeemModal = false"
                                    class="w-full card-inner hover:opacity-80 card-text font-semibold py-3 px-6 rounded-xl transition-colors"
                                >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            @endif

        <script>
            const CARD_SURFACE  = @json($surface);
            const CARD_INNER    = @json($inner);
            const CARD_TEXT     = @json($textOnBg);
            const CARD_MUTED    = @json($mutedOnBg);
            const BRAND_COLOR   = @json($brand);

            document.addEventListener('alpine:init', () => {
                Alpine.data('cardApp', () => ({
                    publicToken: '{{ $account->public_token }}',
                    accountData: null,
                    bannerDismissed: false,
                    showRedeemModal: false,
                    // Wallet nudge
                    showWalletNudge: false,
                    // IMPORTANT:
                    // reward_redeemed_at is a "last redeemed at" timestamp and can be set even when reward_balance > 0
                    // (partial redemption). So we only consider the reward "fully redeemed" when no rewards remain.
                    rewardRedeemed: {{ ($account->reward_redeemed_at && (($account->reward_balance ?? 0) <= 0)) ? 'true' : 'false' }},
                    rewardBalance: {{ $account->reward_balance ?? 0 }},
                    hasRedeemToken: {{ $account->redeem_token ? 'true' : 'false' }},
                    emailVerified: {{ $account->verified_at ? 'true' : 'false' }},
                    verifying: false,
                    verifyMessage: '',
                    cardForgotten: false,
                    // PWA Install Prompt
                    deferredPrompt: null,
                    showInstallPrompt: false,
                    isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream,
                    isAndroid: /Android/.test(navigator.userAgent),
                    isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone || document.referrer.includes('android-app://'),

                    init() {
                        this.persistCard();
                        this.initialize();
                        this.setupPWAInstall();
                        this.initWalletNudge();

                        // Lock body scroll whenever any modal is open
                        this.$watch('showWalletNudge', v => document.body.style.overflow = (v || this.showRedeemModal) ? 'hidden' : '');
                        this.$watch('showRedeemModal', v => document.body.style.overflow = (v || this.showWalletNudge)  ? 'hidden' : '');
                    },

                    initWalletNudge() {
                        const walletKey = 'kawhe_wallet_added_{{ $account->public_token }}';
                        // Never show if they've already added to wallet
                        if (localStorage.getItem(walletKey) === 'true') return;
                        // Always show after 3 seconds — every visit, new or returning
                        setTimeout(() => { this.showWalletNudge = true; }, 3000);
                    },

                    walletAdded() {
                        localStorage.setItem('kawhe_wallet_added_{{ $account->public_token }}', 'true');
                        this.showWalletNudge = false;
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
                            container.innerHTML = `<p class="text-sm text-center" style="color:${CARD_MUTED}">No recent activity</p>`;
                            return;
                        }
                        
                        container.innerHTML = transactions.map(tx => `
                            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color:${CARD_INNER}">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background-color:${BRAND_COLOR}22; color:${BRAND_COLOR}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium" style="color:${CARD_TEXT}">${tx.description}</p>
                                        <p class="text-xs" style="color:${CARD_MUTED}">${tx.formatted_date}</p>
                                    </div>
                                </div>
                                <span class="text-sm font-bold" style="color:${BRAND_COLOR}">
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

                        // Update reward balance (Alpine.js reactive state)
                        const rewardBalance = data.reward_balance ?? 0;
                        const previousRewardBalance = this.rewardBalance;
                        this.rewardBalance = rewardBalance;
                        this.hasRedeemToken = !!data.redeem_token;
                        
                        // Update reward balance display in banner
                        const rewardBalanceBanner = document.getElementById('reward-balance-banner');
                        if (rewardBalanceBanner) {
                            if (rewardBalance > 0) {
                                rewardBalanceBanner.textContent = rewardBalance;
                            } else {
                                rewardBalanceBanner.textContent = '0';
                            }
                        }
                        
                        // Update reward balance display in progress section
                        const rewardBalanceEl = document.getElementById('reward-balance-display');
                        if (rewardBalanceEl) {
                            if (rewardBalance > 0) {
                                rewardBalanceEl.textContent = `Rewards Available: ${rewardBalance}`;
                                rewardBalanceEl.style.display = 'block';
                            } else {
                                rewardBalanceEl.style.display = 'none';
                            }
                        }

                        // Update progress circles - ALWAYS update them, even after redemption
                        // Use the container ID to ensure we're updating the right element
                        const circlesContainer = document.getElementById('stamp-circles-container');
                        if (circlesContainer) {
                            const circles = circlesContainer.querySelectorAll('.stamp-circle');
                            if (circles.length > 0 && data.stamp_count !== undefined && data.reward_target !== undefined) {
                                // Ensure we have the right number of circles
                                const targetCount = data.reward_target;
                                if (circles.length !== targetCount) {
                                    console.warn(`Circle count mismatch: found ${circles.length}, expected ${targetCount}`);
                                }
                                
                                circles.forEach((circle, index) => {
                                    const stampNumber = index + 1;
                                    if (stampNumber <= data.stamp_count) {
                                        circle.className = 'stamp-circle w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold';
                                        circle.style.backgroundColor = BRAND_COLOR;
                                        circle.style.color = '#fff';
                                        circle.style.border = 'none';
                                        circle.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>';
                                    } else {
                                        circle.className = 'stamp-circle w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold';
                                        circle.style.backgroundColor = CARD_INNER;
                                        circle.style.color = CARD_MUTED;
                                        circle.style.border = `2px solid ${CARD_MUTED}44`;
                                        circle.textContent = stampNumber;
                                    }
                                });
                            } else {
                                console.warn('Cannot update circles:', {
                                    container: !!circlesContainer,
                                    circlesLength: circles?.length || 0,
                                    stampCount: data.stamp_count,
                                    rewardTarget: data.reward_target
                                });
                            }
                        } else {
                            console.error('Stamp circles container not found!');
                        }

                        // Check if reward was redeemed
                        if (data.reward_redeemed_at) {
                            // Hide button and banner if no rewards left
                            this.rewardRedeemed = rewardBalance <= 0;
                            this.showRedeemModal = false; // Close modal if open
                            
                            // Reload page after redemption to ensure card state is correct
                            // This fixes issues with stamp circles disappearing
                            setTimeout(() => {
                                window.location.reload();
                            }, 500); // Small delay to show success message
                            return; // Exit early to prevent further updates
                        }

                        // Only reload page if reward FIRST becomes available (0 -> >0), not on redemption
                        // This prevents unnecessary reloads that hide the stamp circles
                        if (data.reward_available && previousRewardBalance === 0 && rewardBalance > 0 && !this.rewardRedeemed) {
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
                    },

                    setupPWAInstall() {
                        // Don't show if already installed
                        if (this.isStandalone) {
                            this.showInstallPrompt = false;
                            return;
                        }

                        // Listen for the beforeinstallprompt event (Chrome, Edge, etc.)
                        window.addEventListener('beforeinstallprompt', (e) => {
                            // Prevent the default mini-infobar from appearing
                            e.preventDefault();
                            // Stash the event so it can be triggered later
                            this.deferredPrompt = e;
                            // Show our install prompt
                            this.showInstallPrompt = true;
                        });

                        // For iOS or if prompt isn't available, show manual instructions
                        if (this.isIOS || (!this.deferredPrompt && !this.isStandalone)) {
                            this.showInstallPrompt = true;
                        }

                        // Hide prompt if user dismissed it before
                        const installDismissed = localStorage.getItem('pwa_install_dismissed');
                        if (installDismissed === 'true') {
                            this.showInstallPrompt = false;
                        }
                    },

                    async installPWA() {
                        if (!this.deferredPrompt) {
                            // Manual instructions already shown in template
                            return;
                        }

                        // Show the install prompt
                        this.deferredPrompt.prompt();

                        // Wait for the user to respond to the prompt
                        const { outcome } = await this.deferredPrompt.userChoice;

                        if (outcome === 'accepted') {
                            console.log('User accepted the install prompt');
                            this.showInstallPrompt = false;
                        } else {
                            console.log('User dismissed the install prompt');
                            // Optionally hide the prompt if user dismissed it
                            localStorage.setItem('pwa_install_dismissed', 'true');
                            this.showInstallPrompt = false;
                        }

                        // Clear the deferredPrompt so it can only be used once
                        this.deferredPrompt = null;
                    }
                }));
            });

            // Apple Wallet download handler
            function downloadAppleWalletPass(url) {
                console.log('Downloading Apple Wallet pass from:', url);
                
                // Try multiple methods for Safari compatibility
                try {
                    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                    if (isIOS) {
                        // iOS Safari handles pkpass best with direct navigation
                        window.location.href = url;
                        return;
                    }

                    // Non-iOS: open in a new window/tab first
                    const newWindow = window.open(url, '_blank');
                    if (!newWindow || newWindow.closed || typeof newWindow.closed === 'undefined') {
                        console.log('Popup blocked, trying direct navigation');
                        window.location.href = url;
                    } else {
                        console.log('Opened in new window');
                    }
                } catch (e) {
                    console.error('Error opening pass:', e);
                    // Fallback: Direct navigation
                    window.location.href = url;
                }
            }
        </script>
    </body>
</html>
