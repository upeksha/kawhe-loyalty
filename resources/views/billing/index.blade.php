<x-merchant-layout>
    <x-slot name="header">
        {{ __('Billing & Subscription') }}
    </x-slot>

    <div class="mx-auto space-y-4 sm:space-y-6">
        @if (session('success'))
            <x-ui.card class="border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
            </x-ui.card>
        @endif

        @if (session('info'))
            <x-ui.card class="border border-brand-200 bg-brand-50 p-4">
                <p class="text-sm font-medium text-brand-800">{{ session('info') }}</p>
            </x-ui.card>
        @endif

        @if ($errors->any())
            <x-ui.card class="border border-red-200 bg-red-50 p-4">
                <div class="flex gap-3">
                    <svg class="h-5 w-5 flex-shrink-0 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="text-sm font-medium text-red-800">We couldn’t complete that billing action.</h3>
                        <ul class="mt-2 list-disc list-inside text-sm text-red-700">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </x-ui.card>
        @endif

        @php
            $billingBlocked = !($stats['can_accept_new_customer'] ?? true) && !($stats['is_subscribed'] ?? false);
            $stripeConfigReady = !empty(config('cashier.key')) && !empty(config('cashier.secret')) && !empty(config('cashier.price_id'));
            $proStores = config('billing.plans.pro.stores');
            $proCards = config('billing.plans.pro.programs_per_store');
            $storesUsed = (int) ($stats['stores_count'] ?? 0);
            $storesLimit = $stats['stores_limit'] ?? null;
            $cardsUsed = (int) ($stats['primary_store_programs_count'] ?? $stats['programs_count'] ?? 0);
            $cardsLimit = $stats['programs_per_store_limit'] ?? null;
            $customersUsed = (int) ($stats['primary_program_customers_count'] ?? 0);
            $customersLimit = $stats['customers_per_program_limit'] ?? null;

            if ($stats['is_subscribed']) {
                $heroTone = 'border-brand-200 bg-gradient-to-br from-brand-50 via-white to-emerald-50';
                $heroBadgeTone = 'bg-brand-100 text-brand-800';
                $heroTitle = 'Pro plan active';
                $heroBody = "Pro includes up to {$proStores} stores, {$proCards} loyalty cards per store, and unlimited customers per card.";
                $heroActionLabel = 'Manage Subscription';
                $heroActionRoute = route('billing.portal');
                $heroActionMethod = 'post';
            } elseif ($billingBlocked) {
                $heroTone = 'border-red-200 bg-gradient-to-br from-red-50 via-white to-orange-50';
                $heroBadgeTone = 'bg-red-100 text-red-800';
                $heroTitle = 'You have reached the free customer limit';
                $heroBody = 'Free includes 1 store, 1 loyalty card, and up to 100 customers on that card. Existing customers keep working — upgrade to accept new joins.';
                $heroActionLabel = 'Upgrade to Pro';
                $heroActionRoute = route('billing.checkout');
                $heroActionMethod = 'post';
            } else {
                $heroTone = 'border-stone-200 bg-gradient-to-br from-stone-50 via-white to-brand-50';
                $heroBadgeTone = 'bg-stone-200 text-stone-700';
                $heroTitle = 'You are on the free plan';
                $heroBody = 'Free includes 1 store, 1 loyalty card, and up to 100 customers on that card.';
                $heroActionLabel = 'Upgrade to Pro';
                $heroActionRoute = route('billing.checkout');
                $heroActionMethod = 'post';
            }
        @endphp

        <x-ui.card class="border {{ $heroTone }} p-5 sm:p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Plan State</p>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $heroBadgeTone }}">
                        {{ $planState['label'] ?? ($stats['is_subscribed'] ? 'Pro' : 'Free') }}
                    </span>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-stone-900 sm:text-3xl">{{ $heroTitle }}</h2>
                    <p class="mt-3 text-sm leading-6 text-stone-600 sm:text-base">
                        {{ $heroBody }}
                    </p>
                    @if(isset($planState))
                        <div class="mt-4 rounded-2xl border border-stone-200/80 bg-white/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">What changes next</p>
                            <p class="mt-2 text-sm leading-6 text-stone-700">{{ $planState['transition'] }}</p>
                        </div>
                    @endif
                </div>

                <div class="w-full max-w-md rounded-[28px] border border-stone-200/80 bg-white/90 p-4 sm:p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Main action</p>
                    <p class="mt-2 text-sm leading-6 text-stone-600">{{ $recommendedBillingAction }}</p>

                    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        @if($heroActionMethod === 'post')
                            <form method="POST" action="{{ $heroActionRoute }}">
                                @csrf
                                <x-ui.button type="submit" variant="primary" size="sm" class="w-full sm:w-auto">
                                    {{ $heroActionLabel }}
                                </x-ui.button>
                            </form>
                        @else
                            <form method="POST" action="{{ $heroActionRoute }}">
                                @csrf
                                <x-ui.button type="submit" variant="primary" size="sm" class="w-full sm:w-auto">
                                    {{ $heroActionLabel }}
                                </x-ui.button>
                            </form>
                        @endif

                        @if(!$stats['is_subscribed'] || $billingBlocked || !empty($debugInfo['has_stripe_id']))
                            <form method="POST" action="{{ route('billing.sync') }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" size="sm" class="w-full sm:w-auto">
                                    Sync Billing Status
                                </x-ui.button>
                            </form>
                        @endif

                        @if($stats['is_subscribed'])
                            <x-ui.button href="{{ route('billing.index', ['refresh' => 1]) }}" variant="ghost" size="sm" class="w-full sm:w-auto">
                                Refresh Status
                            </x-ui.button>
                        @endif
                    </div>

                    <div class="mt-4 rounded-2xl border border-stone-200 bg-stone-50/80 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Quick answer</p>
                        <ul class="mt-2 space-y-2 text-sm text-stone-700">
                            <li>Existing customer cards keep working.</li>
                            <li>{{ $stats['is_subscribed'] ? "Up to {$proStores} stores and {$proCards} cards per store." : ($billingBlocked ? 'New customer joins are paused until you upgrade or free up capacity.' : 'You still have room to grow customers on the free plan.') }}</li>
                            <li>{{ $stats['is_subscribed'] ? 'You can change or cancel later from the billing portal.' : 'Upgrading does not reset or remove any customer data.' }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </x-ui.card>

        <x-billing.plan-comparison :stats="$stats" :current-plan="$stats['plan'] ?? 'free'" />

        <x-ui.card class="p-5 sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-stone-900">Usage right now</h3>
                    <p class="mt-1 text-sm text-stone-600">
                        {{ $stats['is_subscribed'] ? 'Pro limits: stores, cards per store, and customers.' : 'Free limits: 1 store, 1 card, 100 customers per card.' }}
                    </p>
                </div>

                @if(!$stats['is_subscribed'])
                    <span class="inline-flex items-center rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">
                        {{ max(0, (int) (($stats['stores_limit'] ?? 1) - ($stats['stores_count'] ?? 0))) }} store slot{{ max(0, (int) (($stats['stores_limit'] ?? 1) - ($stats['stores_count'] ?? 0))) === 1 ? '' : 's' }} left
                    </span>
                @endif
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                <div class="rounded-2xl border border-stone-200 bg-stone-50/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Stores</p>
                    <p class="mt-2 text-xl font-semibold text-stone-900">
                        {{ $storesUsed }}
                        @if($storesLimit)
                            <span class="text-base font-medium text-stone-500">/ {{ $storesLimit }}</span>
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-stone-600">{{ ($stats['can_create_store'] ?? false) ? 'Room for another store' : 'Store limit reached' }}</p>
                    <x-ui.usage-meter class="mt-3" :used="$storesUsed" :limit="$storesLimit" />
                </div>

                <div class="rounded-2xl border border-stone-200 bg-stone-50/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Cards (primary store)</p>
                    <p class="mt-2 text-xl font-semibold text-stone-900">
                        {{ $cardsUsed }}
                        @if($cardsLimit)
                            <span class="text-base font-medium text-stone-500">/ {{ $cardsLimit }}</span>
                        @else
                            <span class="text-base font-medium text-stone-500">/ store</span>
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-stone-600">{{ ($stats['can_create_program'] ?? false) ? 'Can add another card' : 'Card limit reached on this store' }}</p>
                    <x-ui.usage-meter class="mt-3" :used="$cardsUsed" :limit="$cardsLimit" />
                </div>

                <div class="rounded-2xl border border-stone-200 bg-stone-50/80 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Customers (primary card)</p>
                    <p class="mt-2 text-xl font-semibold text-stone-900">
                        {{ $customersUsed }}
                        @if($customersLimit)
                            <span class="text-base font-medium text-stone-500">/ {{ $customersLimit }}</span>
                        @else
                            <span class="text-base font-medium text-stone-500"> unlimited</span>
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-stone-600">{{ $stats['is_subscribed'] ? 'Unlimited new joins on Pro' : 'New joins blocked at 100 on Free' }}</p>
                    <x-ui.usage-meter class="mt-3" :used="$customersUsed" :limit="$customersLimit" />
                </div>
            </div>

            @if(!$stats['is_subscribed'] && ($billingBlocked || ($stats['stores_usage_percentage'] ?? 0) >= 80 || ($stats['programs_usage_percentage'] ?? 0) >= 80 || ($stats['customers_usage_percentage'] ?? 0) >= 80))
                <p class="mt-5 text-sm text-stone-600">
                    Pro unlocks {{ $proStores }} stores, {{ $proCards }} cards per store, and unlimited customers. Existing customers and cards are never removed.
                </p>
            @endif
        </x-ui.card>

        <x-ui.card class="p-5 sm:p-6">
            <details>
                <summary class="cursor-pointer list-none">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-stone-900">Advanced billing help</h3>
                            <p class="mt-1 text-sm text-stone-600">Only open this if checkout, sync, or plan status looks wrong.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">
                            Show details
                        </span>
                    </div>
                </summary>

                <div class="mt-5 space-y-4 border-t border-stone-200 pt-5">
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

                        <div class="rounded-2xl border border-stone-200 bg-stone-50/70 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold text-stone-900">Billing Support Diagnostics</h4>
                                    <p class="mt-1 text-sm text-stone-600">{{ $billingDiagnosticReady }}/{{ $billingDiagnosticTotal }} checks are currently in a good state.</p>
                                </div>
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $billingDiagnosticTone }}">
                                    {{ $billingDiagnosticLabel }}
                                </span>
                            </div>

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
                        </div>
                    @endif

                    @if(!empty($recoveryActions))
                        <div class="rounded-2xl border border-stone-200 bg-stone-50/70 p-4">
                            <p class="text-sm font-semibold text-stone-900">Recommended next step</p>
                            <p class="mt-2 text-sm leading-6 text-stone-600">{{ $recommendedBillingAction }}</p>
                        </div>

                        <div class="rounded-2xl border border-stone-200 bg-stone-50/70 p-4">
                            <p class="text-sm font-semibold text-stone-900">Recovery actions</p>
                            <ul class="mt-2 list-disc list-inside space-y-2 text-sm leading-relaxed text-stone-600">
                                @foreach($recoveryActions as $action)
                                    <li>{{ $action }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($subscription)
                        <div class="rounded-2xl border border-stone-200 bg-stone-50/70 p-4">
                            <p class="text-sm font-semibold text-stone-900">Subscription details</p>
                            <div class="mt-2 space-y-1 text-sm text-stone-600">
                                <p><strong>Status:</strong> <span class="capitalize">{{ $subscription->stripe_status }}</span></p>
                                @if($subscription->ends_at)
                                    <p><strong>Ends:</strong> {{ $subscription->ends_at->format('M d, Y') }}</p>
                                @endif
                            </div>
                        </div>
                    @elseif(isset($debugInfo) && $debugInfo['has_stripe_id'])
                        <div class="rounded-2xl border border-accent-200 bg-accent-50 p-4">
                            <p class="text-sm font-semibold text-accent-800">Subscription sync pending</p>
                            <p class="mt-2 text-sm leading-6 text-accent-700">
                                Your payment may already be complete, but plan status has not synced yet. Sync billing first, then refresh this page if needed.
                            </p>
                        </div>
                    @endif

                    @if(isset($debugInfo) && (app()->environment('local') || config('app.debug')))
                        <details class="rounded-2xl border border-stone-200 bg-stone-50/70 p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-stone-800">Developer debug info</summary>
                            <div class="mt-3 space-y-1 text-xs text-stone-600">
                                <p><strong>Has Stripe ID:</strong> {{ $debugInfo['has_stripe_id'] ? 'Yes' : 'No' }}</p>
                                <p><strong>Stripe ID:</strong> {{ $debugInfo['stripe_id'] ?? 'N/A' }}</p>
                                <p><strong>Subscription Exists:</strong> {{ $debugInfo['subscription_exists'] ? 'Yes' : 'No' }}</p>
                                <p><strong>Subscription Status:</strong> {{ $debugInfo['subscription_status'] ?? 'N/A' }}</p>
                                <p><strong>Is Subscribed (check):</strong> {{ $debugInfo['is_subscribed_check'] ? 'Yes' : 'No' }}</p>
                                <p><strong>Subscriptions Count:</strong> {{ $debugInfo['subscriptions_count'] }}</p>
                            </div>
                        </details>
                    @endif

                    @if((app()->environment('local') || config('app.debug')) && (empty(config('cashier.key')) || empty(config('cashier.secret')) || empty(config('cashier.price_id'))))
                        <details class="rounded-2xl border border-accent-200 bg-accent-50 p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-accent-800">Stripe configuration status</summary>
                            <ul class="mt-3 space-y-1 text-xs text-accent-700">
                                <li>STRIPE_KEY: {{ empty(config('cashier.key')) ? 'Not set' : 'Set' }}</li>
                                <li>STRIPE_SECRET: {{ empty(config('cashier.secret')) ? 'Not set' : 'Set' }}</li>
                                <li>STRIPE_PRICE_ID: {{ empty(config('cashier.price_id')) ? 'Not set' : 'Set' }}</li>
                            </ul>
                        </details>
                    @endif
                </div>
            </details>
        </x-ui.card>
    </div>
</x-merchant-layout>
