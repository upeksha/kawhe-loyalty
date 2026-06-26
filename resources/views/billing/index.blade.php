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
            $proStores = config('billing.plans.pro.stores');
            $proCards = config('billing.plans.pro.programs_per_store');
            $storesUsed = (int) ($stats['stores_count'] ?? 0);
            $storesLimit = $stats['stores_limit'] ?? null;
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
            } elseif ($billingBlocked) {
                $heroTone = 'border-red-200 bg-gradient-to-br from-red-50 via-white to-orange-50';
                $heroBadgeTone = 'bg-red-100 text-red-800';
                $heroTitle = 'You have reached the free customer limit';
                $heroBody = 'Free includes 1 store, 1 loyalty card, and up to 100 customers on that card. Existing customers keep working — upgrade to accept new joins.';
                $heroActionLabel = 'Upgrade to Pro';
                $heroActionRoute = route('billing.checkout');
            } else {
                $heroTone = 'border-stone-200 bg-gradient-to-br from-stone-50 via-white to-brand-50';
                $heroBadgeTone = 'bg-stone-200 text-stone-700';
                $heroTitle = 'You are on the free plan';
                $heroBody = 'Free includes 1 store, 1 loyalty card, and up to 100 customers on that card.';
                $heroActionLabel = 'Upgrade to Pro';
                $heroActionRoute = route('billing.checkout');
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
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        @if($stats['is_subscribed'])
                            Manage your subscription, payment method, and invoices in the billing portal.
                        @elseif($billingBlocked)
                            Upgrade to Pro to accept new customer joins and unlock higher store and card limits.
                        @else
                            Upgrade when you need more stores, loyalty cards per store, or unlimited customers.
                        @endif
                    </p>

                    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <form method="POST" action="{{ $heroActionRoute }}">
                            @csrf
                            <x-ui.button type="submit" variant="primary" size="sm" class="w-full sm:w-auto">
                                {{ $heroActionLabel }}
                            </x-ui.button>
                        </form>

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

                <div class="rounded-2xl border border-stone-200 bg-stone-50/80 p-4 lg:row-span-1">
                    <x-ui.store-cards-usage
                        :stores="$stats['stores_card_usage'] ?? []"
                        :limit="$cardsLimit"
                        :is-subscribed="(bool) ($stats['is_subscribed'] ?? false)"
                        :can-create-program="(bool) ($stats['can_create_program'] ?? false)"
                    />
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
    </div>
</x-merchant-layout>
