@props([
    'stats' => [],
    'currentPlan' => 'free',
])

@php
    $needsPaymentAttention = in_array(auth()->user()?->subscription('default')?->stripe_status, ['past_due', 'unpaid', 'incomplete', 'paused'], true);
    $plans = config('billing.plans', []);
    $formatLimit = fn ($value, string $suffix = '') => $value === null ? 'Unlimited' : $value.$suffix;

    $rows = [
        ['label' => 'Stores', 'key' => 'stores', 'suffix' => ''],
        ['label' => 'Loyalty cards', 'key' => 'programs_per_store', 'suffix' => ' per store'],
        ['label' => 'Customers', 'key' => 'customers_per_program', 'suffix' => ' per card'],
    ];

    $planOrder = ['free', 'pro', 'business'];
@endphp

<section aria-labelledby="compare-plans-heading" class="space-y-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 id="compare-plans-heading" class="text-xl font-semibold text-stone-900">Compare plans</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-stone-600">
                Existing stores, cards, and customers keep working. Limits only block new stores, cards, or customer joins.
            </p>
        </div>
        @if(!$needsPaymentAttention && !($stats['is_subscribed'] ?? false))
            <form method="POST" action="{{ route('billing.checkout') }}">
                @csrf
                <x-ui.button type="submit" variant="primary" size="md">Upgrade to Pro</x-ui.button>
            </form>
        @endif
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        @foreach($planOrder as $planKey)
            @php
                $plan = $plans[$planKey] ?? [];
                $isCurrent = $currentPlan === $planKey;
                $isComingSoon = (bool) ($plan['coming_soon'] ?? false);
                $label = $plan['label'] ?? ucfirst($planKey);
            @endphp
            <div @class([
                'relative flex flex-col rounded-2xl border p-5 sm:p-6',
                'border-brand-300 bg-white' => $isCurrent && !$isComingSoon,
                'border-stone-200 bg-white' => !$isCurrent || $isComingSoon,
                'opacity-90' => $isComingSoon,
            ])>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-stone-900">{{ $label }}</h3>
                        @if($planKey === 'free')
                            <p class="mt-1 text-sm text-stone-600">Get started at no cost</p>
                        @elseif($planKey === 'pro')
                            <p class="mt-1 text-sm text-stone-600">For growing cafés</p>
                        @else
                            <p class="mt-1 text-sm text-stone-600">For multi-location brands</p>
                        @endif
                    </div>
                    @if($isComingSoon)
                        <x-ui.badge variant="default">Coming soon</x-ui.badge>
                    @elseif($isCurrent)
                        <x-ui.badge variant="success">Current</x-ui.badge>
                    @elseif($planKey === 'pro')
                        <x-ui.badge variant="info">Popular</x-ui.badge>
                    @endif
                </div>

                <ul class="mt-5 flex-1 space-y-3 text-sm text-stone-700">
                    @foreach($rows as $row)
                        <li class="flex items-start justify-between gap-3 py-1">
                            <span class="text-stone-600">{{ $row['label'] }}</span>
                            <span class="font-semibold text-stone-900 text-right">{{ $formatLimit($plan[$row['key']] ?? null, $row['suffix']) }}</span>
                        </li>
                    @endforeach
                    @if($planKey === 'business')
                        <li class="text-sm text-stone-600">
                            Advanced options and more than 3 stores — details at launch.
                        </li>
                    @endif
                </ul>

                <div class="mt-5">
                    @if($isComingSoon)
                        <x-ui.button type="button" variant="secondary" size="md" class="w-full" disabled>
                            Notify me
                        </x-ui.button>
                    @elseif($planKey === 'pro' && !$needsPaymentAttention && !($stats['is_subscribed'] ?? false))
                        <form method="POST" action="{{ route('billing.checkout') }}">
                            @csrf
                            <x-ui.button type="submit" variant="primary" size="md" class="w-full">
                                Choose Pro
                            </x-ui.button>
                        </form>
                    @elseif($planKey === 'pro' && (($stats['is_subscribed'] ?? false) || $needsPaymentAttention))
                        <form method="POST" action="{{ route('billing.portal') }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" size="md" class="w-full">
                                Manage Pro
                            </x-ui.button>
                        </form>
                    @else
                        <p class="text-center text-xs text-stone-500">Included when you sign up</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
