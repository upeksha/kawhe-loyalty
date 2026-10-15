@php($coffeeSample = \App\Support\CoffeeCardSample::enabled($account))
<x-customer-layout
    :account="$account"
    :document-title="$account->store->name . ' – My Card'"
    :manifest-href="route('card.manifest', ['public_token' => $account->public_token])"
    shell="card"
    title="My Card"
    x-data="cardApp()"
    x-init="init()"
>
    <x-slot name="brand"></x-slot>
    <header class="mb-6">
        <h1 class="customer-page-heading" x-text="rewardBalance > 0 ? 'Your reward is ready' : 'Your loyalty card'">Your loyalty card</h1>
        <p class="customer-page-description">{{ $account->store->name }}</p>
        <p x-show="rewardBalance > 0" x-cloak class="customer-page-description" x-text="`${rewardBalance} ${rewardBalance === 1 ? 'reward' : 'rewards'} available`"></p>
    </header>
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

                @if(session('registered'))
                    <div
                        x-data="{ showWelcome: true }"
                        x-show="showWelcome"
                        x-transition
                        class="customer-welcome-banner rounded-xl p-4 mb-4 shadow-lg border-2"
                    >
                        <div class="flex items-start gap-3">
                            <div class="customer-welcome-icon flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-lg">
                                ✓
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-base">You're in!</p>
                                <p class="text-sm mt-1 card-muted">
                                    Collect {{ $account->reward_target }} stamps to earn {{ $account->reward_title }}.
                                    Show this QR code when you visit to collect stamps.
                                </p>
                            </div>
                            <button type="button" @click="showWelcome = false" class="flex-shrink-0 text-sm opacity-70 hover:opacity-100" aria-label="Dismiss">
                                ✕
                            </button>
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
                
                @if($coffeeSample)
                    <style>
                        .loyalty-card{background:#263e30!important;color:#f2ecd7!important}
                        .loyalty-card .card-text,.loyalty-card .card-muted{color:#f2ecd7!important}
                        .loyalty-card #stamp-circles-container{display:none}
                        .loyalty-card .card-text,.loyalty-card .card-muted{overflow-wrap:anywhere;white-space:normal}
                        .coffee-sample-art{display:block;width:100%;height:auto;margin:12px 0}
                    </style>
                @endif
                <!-- Main Loyalty Card -->
                <div class="loyalty-card rounded-2xl overflow-hidden mb-6">
                    
                    <div class="p-6 relative">
                        <div class="mb-6 flex items-center gap-3">
                            @if($account->program_logo_url)
                                <img src="{{ $account->program_logo_url }}" alt="{{ $account->store->name }} logo" class="program-logo-frame h-14 w-14 rounded-full border-2 bg-white/10 object-contain p-1.5">
                            @endif
                            <div class="min-w-0 text-left">
                                <p class="card-muted text-sm font-semibold">{{ $account->store->name }}</p>
                                <h2 class="card-text text-xl font-semibold">{{ $account->program_name }}</h2>
                                <p id="customer-name" class="card-muted text-sm break-words">{{ $account->customer->name ?? 'Valued Customer' }}</p>
                            </div>
                        </div>

                        @if($coffeeSample)
                            <img id="coffee-sample-art" class="coffee-sample-art" src="{{ \App\Support\StoreAssets::url(\App\Support\CoffeeCardSample::artwork($account, 'web')) }}" alt="{{ $account->stamp_count }} of {{ $account->reward_target }} stamps; {{ $account->reward_balance }} rewards available">
                        @endif
                        <!-- Progress Section -->
                        <div class="mb-6">
                            <p id="stamp-count" class="card-text text-[26px] font-semibold">{{ $account->stamp_count }} of {{ $account->reward_target }} stamps</p>
                            <p x-show="rewardBalance <= 0" x-cloak id="reward-title" class="card-muted mt-2 mb-5 text-xs">
                                {{ max(0, $account->reward_target - $account->stamp_count) }} more until {{ strtolower($account->reward_title) }}
                            </p>
                            <p x-show="rewardBalance > 0" x-cloak id="reward-balance-display" class="mt-1 text-center text-base font-semibold text-yellow-300">
                                Your {{ strtolower($account->reward_title) }} is ready
                            </p>
                            <!-- Circular Checkmarks Row -->
                            @unless($coffeeSample)
                            <div id="stamp-circles-container" class="flex gap-2 flex-wrap">
                                @for ($i = 1; $i <= $account->reward_target; $i++)
                                    @if($i <= $account->stamp_count)
                                        <div class="stamp-circle stamp-circle--filled w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="stamp-circle stamp-circle--empty w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold">
                                            {{ $i }}
                                        </div>
                                    @endif
                                @endfor
                            </div>
                            @endunless
                        </div>

                        <div class="customer-card-code flex flex-col items-center">
                            <p class="card-text mb-3 text-center text-xs" x-text="rewardBalance > 0 ? 'Show this code to redeem your reward' : 'Show this code when you buy a coffee'"></p>
                            <div class="customer-qr-panel rounded-xl bg-white p-4">
                                <div id="stamp-qr-container" class="customer-qr">
                                    {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(220)->errorCorrection('L')->margin(2)->generate('LA:' . $account->public_token) !!}
                                </div>
                            </div>
                            @if($account->manual_entry_code ?? null)
                                <p class="card-muted mt-3 text-xs">Can’t scan?</p>
                                <p class="card-text mt-1 text-center text-sm">Manual code · <strong class="font-mono text-base tracking-[0.2em]">{{ $account->manual_entry_code }}</strong></p>
                            @endif
                        </div>

                        <div x-show="rewardBalance > 0 && hasRedeemToken" x-cloak class="mt-5">
                            <button @click="showRedeemModal = true" class="flex min-h-12 w-full items-center justify-center rounded-xl bg-yellow-500 px-5 py-3 font-bold text-stone-950 transition hover:bg-yellow-400">
                                Redeem reward <span x-show="rewardBalance > 1" class="ml-1" x-text="`(${rewardBalance} available)`"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="customer-actions mb-6">
                    <button type="button" @click="showWalletNudge = true" class="customer-btn customer-btn-primary">Save to Wallet</button>
                    <div x-data="cardInstall" data-card-install x-show="visible || message" x-cloak>
                        <button type="button" x-show="visible" x-cloak @click="install()" :disabled="installing"
                            :aria-expanded="showHelp.toString()" aria-controls="card-install-help"
                            class="customer-btn customer-btn-secondary" x-text="label"></button>
                        <section id="card-install-help" x-show="showHelp && !installed" x-cloak
                            class="mt-3 rounded-xl border border-stone-200 bg-white p-4 text-sm customer-card-body" aria-label="Home screen instructions">
                            <div x-show="ios">
                                <p class="font-semibold">On iPhone or iPad</p>
                                <ol class="mt-2 list-inside list-decimal space-y-2">
                                    <li>Open this card in Safari.</li>
                                    <li>Tap Share, then Add to Home Screen. You may need to scroll through the options.</li>
                                    <li>Tap Add to confirm.</li>
                                </ol>
                            </div>
                            <div x-show="android">
                                <p class="font-semibold">On Android</p>
                                <p class="mt-2">Open your browser menu and look for Install app or Add to Home screen. If neither appears, open this card in Chrome and try again.</p>
                            </div>
                            <p class="mt-3 text-xs">Adding a shortcut is optional. You can keep using your card in the browser.</p>
                            <button type="button" @click="showHelp = false" class="mt-2 min-h-11 font-semibold text-brand-700">Close instructions</button>
                        </section>
                        <p x-show="message" x-text="message" role="status" aria-live="polite" class="mt-2 text-sm customer-muted"></p>
                    </div>
                    <button type="button" @click="refreshCard()" :disabled="refreshing" class="customer-btn customer-btn-secondary" x-text="refreshing ? 'Refreshing…' : 'Refresh card'">Refresh card</button>
                    <p x-show="refreshMessage" x-text="refreshMessage" class="customer-muted text-sm" role="status" aria-live="polite"></p>
                </div>

                @if(!$account->verified_at && $account->customer->email)
                    <section class="card-surface mb-4 rounded-2xl p-5 shadow-xl" aria-labelledby="verification-title">
                        <h2 id="verification-title" class="card-text text-lg font-semibold">Your card is active</h2>
                        <p class="card-muted mt-1 text-sm leading-6">You can collect stamps now. Verify your email before redeeming a reward.</p>
                        <button type="button" @click="sendVerification()" :disabled="verifying" class="card-btn mt-4 min-h-11 w-full rounded-xl px-4 py-2.5 text-sm font-semibold disabled:opacity-60">
                            <span x-text="verifying ? 'Sending…' : 'Send verification email'"></span>
                        </button>
                        <p class="card-muted mt-2 text-xs">Check your inbox and spam folder. Expired links can be replaced by sending a new email.</p>
                        <p x-show="verifyMessage" x-text="verifyMessage" class="mt-2 text-sm font-semibold" role="status" aria-live="polite"></p>
                    </section>
                @endif

                <section class="card-surface mb-4 rounded-2xl p-5 shadow-xl" aria-labelledby="recent-activity-title">
                    <h2 id="recent-activity-title" class="card-text text-base font-semibold">Recent activity</h2>
                    <div id="transaction-history" class="mt-3 space-y-2" aria-live="polite">
                        <p class="card-muted py-2 text-center text-sm">Loading recent activity…</p>
                    </div>
                </section>

                <!-- Forget Card Link -->
                <div class="text-center">
                    <button @click="forgetCard()" x-show="!cardForgotten" class="customer-btn customer-btn-secondary">
                        Forget This Card
                    </button>
                    <p x-show="cardForgotten" class="text-emerald-800 text-sm font-medium">
                        ✓ Card removed from this device
                    </p>
                </div>

            @push('overlays')
        <!-- ============================================================ -->
        <!-- Wallet Nudge Modal — shown after first join when wallet not yet added -->
            <!-- ============================================================ -->
            <div
                x-show="showWalletNudge"
                x-cloak
                @keydown.escape.window="dismissWalletNudge()"
                class="fixed inset-0 z-[100] flex flex-col"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
            >
                <!-- Backdrop (only visible on sm+, on mobile the sheet fills the screen) -->
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm sm:block" @click="dismissWalletNudge()"></div>

                <!-- Full-height sheet on mobile, centred card on sm+ -->
                <div
                    @click.stop
                    role="dialog" aria-modal="true" aria-labelledby="wallet-title" class="wallet-sheet relative flex flex-col w-full h-full sm:h-auto sm:max-w-md sm:mx-auto sm:my-auto sm:rounded-3xl sm:max-h-[90vh] shadow-2xl overflow-hidden"
                    x-transition:enter="transition ease-out duration-350"
                    x-transition:enter-start="opacity-0 translate-y-6"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-6"
                >
                    <div class="wallet-sheet-accent flex-shrink-0 h-1 w-full"></div>

                    <!-- Close button -->
                    <button
                        @click="dismissWalletNudge()"
                        class="wallet-sheet-close absolute top-4 right-4 z-10 w-9 h-9 flex items-center justify-center rounded-full transition hover:opacity-80"
                        aria-label="Close"
                    >
                        <svg class="w-5 h-5 card-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    <!-- Scrollable content -->
                    <div class="flex-1 overflow-y-auto px-6 pt-6 pb-safe-bottom" style="padding-bottom: max(2rem, env(safe-area-inset-bottom));">
                        <div class="customer-brand"><img src="{{ asset('images/logo.svg') }}" width="28" height="37" alt=""><span>kawhe</span></div>
                        <h2 id="wallet-title" class="customer-page-heading mb-2">Keep your card handy</h2>
                        <p class="customer-page-description mb-6">Save your card to Wallet for quick access when you visit {{ $account->store->name }}.</p>

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
                            @click="dismissWalletNudge()"
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
                        role="dialog" aria-modal="true" aria-labelledby="redeem-title" class="card-surface rounded-2xl p-6 max-w-sm w-full mx-4 relative max-h-[90dvh] overflow-y-auto"
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
                            aria-label="Close reward" class="absolute top-4 right-4 card-muted hover:opacity-80 transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>

                        <!-- Modal Content -->
                        <div class="text-center">
                                <div class="mb-4">
                                    <h2 id="redeem-title" class="customer-page-heading card-text mb-2 pr-5">Redeem your reward</h2>
                                    <p class="card-muted text-sm">Show this QR code to staff. Opening it does not spend your reward.</p>
                                </div>

                                <!-- QR Code -->
                                <div class="bg-white rounded-xl p-4 border border-stone-200 mb-4 flex flex-col items-center">
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
                                    Present this QR code to claim your <span class="font-semibold card-reward-copy">{{ $account->reward_title }}</span>
                                </p>
                                @if(($account->reward_balance ?? 0) > 1)
                                    <p class="card-reward-copy text-xs text-center mb-2">
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
            @endpush

        @push('scripts')
        <script>
            const themePage = document.querySelector('.customer-page');
            const themeVar = (name) => themePage ? getComputedStyle(themePage).getPropertyValue(name).trim() : '';
            const BRAND_COLOR = themeVar('--program-brand');

            document.addEventListener('alpine:init', () => {
                Alpine.data('cardApp', () => ({
                    publicToken: '{{ $account->public_token }}',
                    accountData: null,
                    bannerDismissed: false,
                    showRedeemModal: false,
                    // Wallet nudge
                    showWalletNudge: false,
                    justRegistered: @json(session('show_wallet_nudge', false)),
                    // IMPORTANT:
                    // reward_redeemed_at is a "last redeemed at" timestamp and can be set even when reward_balance > 0
                    // (partial redemption). So we only consider the reward "fully redeemed" when no rewards remain.
                    rewardRedeemed: {{ ($account->reward_redeemed_at && (($account->reward_balance ?? 0) <= 0)) ? 'true' : 'false' }},
                    rewardBalance: {{ $account->reward_balance ?? 0 }},
                    hasRedeemToken: {{ $account->redeem_token ? 'true' : 'false' }},
                    emailVerified: {{ $account->verified_at ? 'true' : 'false' }},
                    refreshing: false,
                    refreshMessage: '',
                    verifying: false,
                    verifyMessage: '',
                    cardForgotten: false,
                    isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream,
                    isAndroid: /Android/.test(navigator.userAgent),

                    init() {
                        this.persistCard();
                        this.initialize();
                        this.initWalletNudge();

                        // Lock body scroll whenever any modal is open
                        this.$watch('showWalletNudge', v => document.body.style.overflow = (v || this.showRedeemModal) ? 'hidden' : '');
                        this.$watch('showRedeemModal', v => document.body.style.overflow = (v || this.showWalletNudge)  ? 'hidden' : '');
                    },

                    initWalletNudge() {
                        const walletKey = 'kawhe_wallet_added_{{ $account->public_token }}';
                        if (localStorage.getItem(walletKey) === 'true') {
                            return;
                        }

                        const dismissedKey = 'kawhe_wallet_nudge_dismissed_{{ $account->public_token }}';
                        if (! this.justRegistered || localStorage.getItem(dismissedKey) === 'true') {
                            return;
                        }

                        setTimeout(() => { this.showWalletNudge = true; }, 2000);
                    },

                    dismissWalletNudge() {
                        localStorage.setItem('kawhe_wallet_nudge_dismissed_{{ $account->public_token }}', 'true');
                        this.showWalletNudge = false;
                    },

                    walletAdded() {
                        localStorage.setItem('kawhe_wallet_added_{{ $account->public_token }}', 'true');
                        localStorage.setItem('kawhe_wallet_nudge_dismissed_{{ $account->public_token }}', 'true');
                        this.showWalletNudge = false;
                    },

                    persistCard() {
                        const programKey = '{{ $account->loyalty_program_id ?? $account->store_id }}';
                        localStorage.setItem('kawhe_last_card_' + programKey, this.publicToken);
                        @if($account->customer->email)
                            localStorage.setItem('kawhe_last_email_' + programKey, '{{ $account->customer->email }}');
                        @endif
                        // A default program can share its numeric ID with its store.
                        if (programKey !== '{{ $account->store_id }}') {
                            localStorage.removeItem('kawhe_last_card_{{ $account->store_id }}');
                            localStorage.removeItem('kawhe_last_email_{{ $account->store_id }}');
                        }
                    },

                    forgetCard() {
                        try {
                            const programKey = '{{ $account->loyalty_program_id ?? $account->store_id }}';
                            localStorage.removeItem('kawhe_last_card_' + programKey);
                            localStorage.removeItem('kawhe_last_email_' + programKey);
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
                            container.innerHTML = '<p class="text-sm text-center card-muted">No recent activity</p>';
                            return;
                        }
                        
                        container.innerHTML = transactions.map(tx => `
                            <div class="tx-history-item flex items-center justify-between p-3 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="tx-history-icon w-8 h-8 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="tx-history-title text-sm font-medium">${tx.description}</p>
                                        <p class="tx-history-date text-xs">${tx.formatted_date}</p>
                                    </div>
                                </div>
                                <span class="tx-history-points text-sm font-bold">${tx.points > 0 ? '+' : ''}${tx.points}</span>
                            </div>
                        `).join('');
                    },

                    async updateUI(data) {
                        // Update stamp count
                        const stampCountEl = document.getElementById('stamp-count');
                        if (stampCountEl) {
                            stampCountEl.textContent = `${data.stamp_count} of ${data.reward_target} stamps`;
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
                                rewardBalanceEl.textContent = `Your ${String(data.reward_title || 'reward').toLowerCase()} is ready`;
                                rewardBalanceEl.style.display = 'block';
                            } else {
                                rewardBalanceEl.style.display = 'none';
                            }
                        }

                        const progressCopyEl = document.getElementById('reward-title');
                        if (progressCopyEl && rewardBalance <= 0) {
                            progressCopyEl.textContent = `${Math.max(0, data.reward_target - data.stamp_count)} more until ${String(data.reward_title || 'your reward').toLowerCase()}`;
                        }

                        const coffeeArt = document.getElementById('coffee-sample-art');
                        if (coffeeArt) {
                            if (data.coffee_artwork_url) coffeeArt.src = data.coffee_artwork_url;
                            coffeeArt.alt = `${data.stamp_count} of ${data.reward_target} stamps; ${data.reward_balance} rewards available`;
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
                                        circle.className = 'stamp-circle stamp-circle--filled w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold';
                                        circle.style.backgroundColor = '';
                                        circle.style.color = '';
                                        circle.style.border = '';
                                        circle.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>';
                                    } else {
                                        circle.className = 'stamp-circle stamp-circle--empty w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold';
                                        circle.style.backgroundColor = '';
                                        circle.style.color = '';
                                        circle.style.border = '';
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
                            if (!coffeeArt) console.error('Stamp circles container not found!');
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

                    async refreshCard() {
                        if (this.refreshing) return;
                        this.refreshing = true;
                        this.refreshMessage = '';
                        try {
                            this.refreshMessage = await this.refreshCardWithRetry()
                                ? 'Your card is up to date.'
                                : 'Could not refresh. Check your connection and try again.';
                        } finally {
                            this.refreshing = false;
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
                                return true;
                            } catch (error) {
                                if (attempt === maxRetries) {
                                    console.error('Failed to refresh card after', maxRetries, 'attempts');
                                } else {
                                    await new Promise(resolve => setTimeout(resolve, 1000 * attempt));
                                }
                            }
                        }
                        return false;
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
        @endpush
</x-customer-layout>
