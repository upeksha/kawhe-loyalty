<x-merchant-layout>
    <x-slot name="header">
        {{ __('Billing & Subscription') }}
    </x-slot>

    <div class="max-w-4xl mx-auto space-y-4 sm:space-y-6">
        @if (session('success'))
            <x-ui.card class="p-4 bg-emerald-50 border border-emerald-200">
                <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
            </x-ui.card>
        @endif

        @if (session('info'))
            <x-ui.card class="p-4 bg-brand-50 border border-brand-200">
                <p class="text-sm font-medium text-brand-800">{{ session('info') }}</p>
            </x-ui.card>
        @endif

        <!-- Error Messages -->
        @if ($errors->any())
            <x-ui.card class="p-4 bg-red-50 border border-red-200">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        @endif
        
        @php
            $remainingCards = max(0, (int) (($stats['limit'] ?? 0) - ($stats['non_grandfathered_count'] ?? 0)));
            $usagePercent = min(100, max(0, (int) ($stats['usage_percentage'] ?? 0)));
        @endphp

        @if(isset($planState))
            <x-ui.card class="p-4 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-stone-900">Plan State</h2>
                        <p class="mt-1 text-sm text-stone-600">A plain-language summary of where billing stands right now.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $planState['tone'] }}">
                        {{ $planState['label'] }}
                    </span>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-stone-200 bg-stone-50/70 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Current state</p>
                        <p class="mt-2 text-sm leading-relaxed text-stone-700">{{ $planState['summary'] }}</p>
                    </div>
                    <div class="rounded-xl border border-stone-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">What changes next</p>
                        <p class="mt-2 text-sm leading-relaxed text-stone-700">{{ $planState['transition'] }}</p>
                    </div>
                </div>
            </x-ui.card>
        @endif

        <x-ui.card class="p-4 sm:p-6">
            @if(isset($billingDiagnostics))
                @php
                    $billingDiagnosticReady = collect($billingDiagnostics)->where('ready', true)->count();
                    $billingDiagnosticTotal = count($billingDiagnostics);
                    $billingDiagnosticLabel = $billingDiagnosticReady === $billingDiagnosticTotal
                        ? 'Healthy'
                        : ($billingDiagnosticReady >= 2 ? 'Needs attention' : 'Needs review');
                    $billingDiagnosticTone = $billingDiagnosticReady === $billingDiagnosticTotal
                        ? 'bg-emerald-100 text-emerald-700'
                        : ($billingDiagnosticReady >= 2 ? 'bg-amber-100 text-amber-700' : 'bg-accent-100 text-accent-700');
                @endphp

                <div class="mb-6 rounded-xl border border-stone-200 bg-stone-50/70 p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-base sm:text-lg font-bold text-stone-900">Billing Support Diagnostics</h3>
                            <p class="mt-1 text-sm text-stone-600">Use this when checkout, sync, or new-customer capacity looks wrong.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $billingDiagnosticTone }}">
                            {{ $billingDiagnosticLabel }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm text-stone-600">{{ $billingDiagnosticReady }}/{{ $billingDiagnosticTotal }} billing checks are in a strong place.</p>
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach($billingDiagnostics as $diagnostic)
                            <li class="rounded-xl border border-stone-200 bg-white px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-medium text-stone-800">{{ $diagnostic['label'] }}</span>
                                    <span class="font-medium {{ $diagnostic['ready'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                        {{ $diagnostic['ready'] ? 'Ready' : 'Review' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs leading-relaxed text-stone-500">{{ $diagnostic['hint'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-4 rounded-xl border border-stone-200 bg-white p-4">
                        <p class="text-sm font-semibold text-stone-800">Recommended next step</p>
                        <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ $recommendedBillingAction }}</p>
                    </div>

                    @if(!empty($recoveryActions))
                        <div class="mt-4 rounded-xl border border-stone-200 bg-white p-4">
                            <p class="text-sm font-semibold text-stone-800">Recovery actions</p>
                            <ul class="mt-2 space-y-2 text-sm leading-relaxed text-stone-600 list-disc list-inside">
                                @foreach($recoveryActions as $action)
                                    <li>{{ $action }}</li>
                                @endforeach
                            </ul>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('billing.sync') }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm">
                                        Sync From Stripe
                                    </x-ui.button>
                                </form>
                                @if(!empty($debugInfo['has_stripe_id']))
                                    <x-ui.button href="{{ route('billing.index', ['refresh' => 1]) }}" variant="ghost" size="sm">
                                        Refresh Status
                                    </x-ui.button>
                                @endif
                                @if($stats['is_subscribed'])
                                    <form method="POST" action="{{ route('billing.portal') }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="ghost" size="sm">
                                            Open Billing Portal
                                        </x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Current Plan Status -->
            <div class="mb-6">
                <h3 class="text-base sm:text-lg font-bold text-stone-900 mb-3 sm:mb-4">Current Plan</h3>
                @if($stats['is_subscribed'])
                    <div class="p-4 bg-brand-50 border border-brand-200 rounded-lg">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <p class="text-brand-800 font-semibold">✓ Pro Plan Active</p>
                                <p class="text-sm text-brand-700 mt-1">Unlimited customer cards and uninterrupted signups.</p>
                                <p class="text-xs text-brand-700 mt-2">If you cancel later, your current billing period stays active. New joins only fall back to the free-plan limit after the subscription actually ends.</p>
                            </div>
                            <form method="POST" action="{{ route('billing.portal') }}">
                                @csrf
                                <x-ui.button type="submit" variant="primary" size="sm">
                                    Manage Subscription
                                </x-ui.button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="p-4 bg-stone-50 border border-stone-200 rounded-lg">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <p class="text-stone-800 font-semibold">Free Plan</p>
                                <p class="text-sm text-stone-600 mt-1">
                                    {{ $stats['cards_count'] }} / {{ $stats['limit'] }} cards used for new signups
                                    @if($stats['grandfathered_count'] > 0)
                                        <span class="text-xs text-stone-500">({{ $stats['grandfathered_count'] }} grandfathered still active)</span>
                                    @endif
                                </p>
                                <p class="text-xs text-stone-500 mt-1">Existing customers can always use their cards. Limit only affects new joins.</p>
                                <p class="text-xs text-stone-500 mt-1">Upgrading does not remove or reset any customer cards. It only restores unlimited join capacity.</p>
                                <p class="text-xs text-stone-600 mt-1"><strong>{{ $remainingCards }}</strong> new signup slot{{ $remainingCards === 1 ? '' : 's' }} remaining.</p>
                            </div>
                            @if($stats['non_grandfathered_count'] >= $stats['limit'])
                                <div class="w-full sm:w-auto text-left sm:text-right">
                                    <p class="text-sm text-red-600 font-semibold mb-2">Limit Reached</p>
                                    <form method="POST" action="{{ route('billing.checkout') }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="primary" size="sm" class="w-full sm:w-auto">
                                            Upgrade to Resume Signups
                                        </x-ui.button>
                                    </form>
                                </div>
                            @else
                                <form method="POST" action="{{ route('billing.checkout') }}" id="upgrade-form">
                                    @csrf
                                    <x-ui.button type="submit" variant="primary" size="sm" class="w-full sm:w-auto">
                                        Upgrade Before Limit
                                    </x-ui.button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <!-- Usage Statistics -->
            @if(!$stats['is_subscribed'])
                <div class="mb-6">
                    <h3 class="text-base sm:text-lg font-bold text-stone-900 mb-3 sm:mb-4">Usage Right Now</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-stone-600">Loyalty Cards Issued</span>
                            <span class="font-semibold">{{ $stats['cards_count'] }} / {{ $stats['limit'] }}</span>
                        </div>
                        @if($stats['grandfathered_count'] > 0)
                            <p class="text-xs text-brand-600">
                                {{ $stats['grandfathered_count'] }} grandfathered card(s) are excluded from your free-plan limit.
                            </p>
                        @endif
                        <div class="w-full bg-stone-200 rounded-full h-3">
                            <div class="bg-brand-600 h-3 rounded-full transition-all duration-300"
                                 style="width: {{ $stats['usage_percentage'] }}%"></div>
                        </div>
                        <p class="text-xs text-stone-500 mt-1">
                            {{ $remainingCards }} cards remaining on free plan ({{ $usagePercent }}% used)
                        </p>
                    </div>
                    <div class="mt-4 rounded-lg border border-stone-200 bg-stone-50 p-3">
                        <p class="text-xs font-semibold text-stone-700">Upgrade impact</p>
                        <p class="text-xs text-stone-600 mt-1">Upgrading immediately removes the join limit. No customer data is reset or removed.</p>
                    </div>
                    <div class="mt-3 rounded-lg border border-brand-200 bg-brand-50 p-3">
                        <p class="text-xs font-semibold text-brand-800">Decision helper</p>
                        <ul class="mt-1 space-y-1 text-xs text-brand-700">
                            <li>If you are near 0 remaining slots, upgrade now to avoid join interruptions.</li>
                            <li>If you have active campaigns running, upgrade before limit to keep QR onboarding continuous.</li>
                            <li>If growth is low this week, you can stay on free and monitor this page.</li>
                        </ul>
                    </div>
                </div>
            @endif

            <!-- Subscription Details -->
            @if($subscription)
                <div class="mb-6">
                    <h3 class="text-base sm:text-lg font-bold text-stone-900 mb-3 sm:mb-4">Subscription Details</h3>
                    <div class="bg-stone-50 p-4 rounded-lg">
                        <p class="text-sm text-stone-600">
                            <strong>Status:</strong> 
                            <span class="capitalize">{{ $subscription->stripe_status }}</span>
                        </p>
                        @if($subscription->ends_at)
                            <p class="text-sm text-stone-600 mt-2">
                                <strong>Ends:</strong> {{ $subscription->ends_at->format('M d, Y') }}
                            </p>
                        @endif
                        <div class="mt-3">
                            <a href="{{ route('billing.index', ['refresh' => 1]) }}" 
                               class="text-xs text-brand-600 hover:text-brand-700 underline">
                                🔄 Refresh Subscription Status
                            </a>
                        </div>
                        <div class="mt-4 rounded-lg border border-stone-200 bg-white p-3">
                            <p class="text-xs font-semibold text-stone-700">Plan transition note</p>
                            <p class="mt-1 text-xs leading-relaxed text-stone-600">
                                @if($subscription->ends_at)
                                    Your plan will keep working until {{ $subscription->ends_at->format('M d, Y') }}, then new joins return to the free-plan limit automatically.
                                @else
                                    Your plan stays active until you change it in the billing portal. Existing customers are never removed by plan changes.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            @elseif(isset($debugInfo) && $debugInfo['has_stripe_id'])
                <div class="mb-6">
                    <div class="bg-accent-50 border border-accent-200 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-accent-800 mb-2">Subscription Sync Pending</h3>
                        <p class="text-xs text-accent-700 mb-3">
                            Your payment may be complete, but plan status has not synced yet. Use one of the actions below.
                        </p>
                        <div class="space-y-2">
                            <form method="POST" action="{{ route('billing.sync') }}" class="inline">
                                @csrf
                                <x-ui.button type="submit" variant="primary" size="sm" class="w-full sm:w-auto">
                                    Sync From Stripe
                                </x-ui.button>
                            </form>
                            <x-ui.button href="{{ route('billing.index', ['refresh' => 1]) }}" variant="primary" size="sm" class="w-full sm:w-auto">
                                Refresh Status
                            </x-ui.button>
                        </div>
                        <p class="text-xs text-accent-600 mt-3">
                            <strong>Note:</strong> If this persists, check Stripe Dashboard to verify the subscription exists, 
                            then contact support with your Stripe customer ID: <code>{{ $debugInfo['stripe_id'] }}</code>
                        </p>
                    </div>
                </div>
            @endif
            
            <!-- Debug Info (only in development) -->
            @if(isset($debugInfo) && (app()->environment('local') || config('app.debug')))
                <div class="border-t pt-6 mt-6">
                    <details class="bg-stone-50 p-4 rounded-lg">
                        <summary class="text-sm font-semibold text-stone-700 cursor-pointer">🔍 Debug Info</summary>
                        <div class="mt-3 text-xs text-stone-600 space-y-1">
                            <p><strong>Has Stripe ID:</strong> {{ $debugInfo['has_stripe_id'] ? 'Yes' : 'No' }}</p>
                            <p><strong>Stripe ID:</strong> {{ $debugInfo['stripe_id'] ?? 'N/A' }}</p>
                            <p><strong>Subscription Exists:</strong> {{ $debugInfo['subscription_exists'] ? 'Yes' : 'No' }}</p>
                            <p><strong>Subscription Status:</strong> {{ $debugInfo['subscription_status'] ?? 'N/A' }}</p>
                            <p><strong>Is Subscribed (check):</strong> {{ $debugInfo['is_subscribed_check'] ? 'Yes' : 'No' }}</p>
                            <p><strong>Subscriptions Count:</strong> {{ $debugInfo['subscriptions_count'] }}</p>
                        </div>
                    </details>
                </div>
            @endif

            <!-- Upgrade Benefits -->
            @if(!$stats['is_subscribed'])
                <div class="border-t pt-6">
                    <h3 class="text-base sm:text-lg font-bold text-stone-900 mb-3 sm:mb-4">Why Upgrade</h3>
                    <ul class="space-y-2 text-sm text-stone-600">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Unlimited new customer signups
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Existing cards continue without interruption
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-brand-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Cancel anytime from billing portal
                        </li>
                    </ul>
                </div>
            @endif
            
            <!-- Stripe setup details (development only) -->
            @if((app()->environment('local') || config('app.debug')) && (empty(config('cashier.key')) || empty(config('cashier.secret')) || empty(config('cashier.price_id'))))
                <div class="border-t pt-6 mt-6">
                    <details class="bg-accent-50 border border-accent-200 rounded-lg p-4">
                        <summary class="text-sm font-semibold text-accent-800 cursor-pointer">⚠️ Stripe Configuration Status</summary>
                        <ul class="text-xs text-accent-700 space-y-1 mt-3">
                            <li>STRIPE_KEY: {{ empty(config('cashier.key')) ? '❌ Not set' : '✅ Set' }}</li>
                            <li>STRIPE_SECRET: {{ empty(config('cashier.secret')) ? '❌ Not set' : '✅ Set' }}</li>
                            <li>STRIPE_PRICE_ID: {{ empty(config('cashier.price_id')) ? '❌ Not set' : '✅ Set' }}</li>
                        </ul>
                    </details>
                </div>
            @endif
        </x-ui.card>
    </div>
</x-merchant-layout>
