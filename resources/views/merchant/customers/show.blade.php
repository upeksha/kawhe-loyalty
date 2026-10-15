@php
    $customerName = trim($account->customer->name ?? '') ?: trim(($account->customer->first_name ?? '').' '.($account->customer->last_name ?? ''));
    $customerTitle = $customerName ?: ($account->customer->email ?: 'Customer details');
    $extraCustomerDetails = array_filter([
        'First name' => $account->customer->first_name,
        'Last name' => $account->customer->last_name,
        'Birthday' => $account->customer->birthday ? \Carbon\Carbon::parse($account->customer->birthday)->format('M d, Y') : null,
        'Store address' => $account->store->address,
        'Email verified' => $account->verified_at?->format('M d, Y g:i A'),
    ], fn ($value) => filled($value));
@endphp
<x-merchant-design-layout :title="$customerTitle" :subtitle="$account->program_name . ' · ' . $account->store->name">
    <x-slot:actions>
        <x-ui.button href="{{ route('merchant.customers.index', request()->only(['q', 'store_id'])) }}" variant="ghost">← Back to customers</x-ui.button>
        <x-ui.button href="{{ route('merchant.customers.edit', $account) }}" variant="secondary">Edit details</x-ui.button>
    </x-slot:actions>
    @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <section aria-labelledby="customer-details-heading" class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
        <h2 id="customer-details-heading" class="text-lg font-semibold text-stone-900">Customer details</h2>
        <dl class="mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @if($account->customer->email)
                <div class="min-w-0"><dt class="text-sm text-stone-500">Email</dt><dd class="mt-1 break-words text-sm font-medium text-stone-900">{{ $account->customer->email }}</dd></div>
            @endif
            @if($account->customer->phone)
                <div><dt class="text-sm text-stone-500">Phone</dt><dd class="mt-1 break-words text-sm font-medium text-stone-900">{{ $account->customer->phone }}</dd></div>
            @endif
            <div><dt class="text-sm text-stone-500">Joined</dt><dd class="mt-1 text-sm font-medium text-stone-900">{{ $account->created_at->format('M d, Y') }}</dd></div>
            @foreach($extraCustomerDetails as $label => $value)
                <div class="min-w-0"><dt class="text-sm text-stone-500">{{ $label }}</dt><dd class="mt-1 break-words text-sm font-medium text-stone-900">{{ $value }}</dd></div>
            @endforeach
        </dl>
    </section>

    <x-ui.card class="p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-stone-900">Loyalty progress</h2>
        <dl class="mt-5 grid gap-5 sm:grid-cols-3">
            <div><dt class="text-sm text-stone-500">Stamps</dt><dd class="mt-1 text-3xl font-semibold text-stone-900">{{ $account->stamp_count }} <span class="text-lg font-normal text-stone-500">/ {{ $account->reward_target }}</span></dd><p class="mt-2 text-xs text-stone-500">{{ max(0, $account->reward_target - $account->stamp_count) }} more stamps to the next reward</p></div>
            <div><dt class="text-sm text-stone-500">Rewards available</dt><dd class="mt-1 text-3xl font-semibold text-stone-900">{{ $account->reward_balance }}</dd>
                @if($account->reward_balance > 0 && $account->reward_available_at)<p class="mt-2 text-xs text-stone-500">Available since {{ $account->reward_available_at->format('M d, Y') }}</p>@endif
                @if($account->reward_redeemed_at)<p class="mt-2 text-xs text-stone-500">Last redeemed {{ $account->reward_redeemed_at->format('M d, Y g:i A') }}</p>@endif
            </div>
            <div><dt class="text-sm text-stone-500">Last stamped</dt><dd class="mt-2 text-lg font-semibold text-stone-900">{{ $account->last_stamped_at?->format('M d, Y') ?? 'Not recorded' }}</dd>
                @if($account->last_stamped_at)<p class="mt-2 text-xs text-stone-500">{{ $account->last_stamped_at->format('g:i A') }}</p>@endif
            </div>
        </dl>
        <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-stone-200 pt-4">
            <div>
                <x-ui.badge :variant="$account->verified_at ? 'success' : 'warning'">{{ $account->verified_at ? 'Email verified' : 'Email not verified' }}</x-ui.badge>
                @if(!$account->verified_at)
                    <p class="mt-2 text-sm text-stone-600">{{ $account->requires_verification_for_redemption ? 'This customer needs to verify their email before redeeming rewards.' : 'Email verification is optional for redeeming rewards on this card.' }}</p>
                @endif
            </div>
            @if(!$account->verified_at && $account->customer->email)
                <form method="POST" action="{{ route('merchant.customers.resend-verification', $account) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">Resend verification email</x-ui.button>
                </form>
            @endif
        </div>
    </x-ui.card>

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <x-ui.card class="min-w-0 p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-stone-900">Recent activity</h2>
            <p class="mt-1 text-sm text-stone-500">Stamps and rewards recorded for this card.</p>
            @if($events->isEmpty())
                <p class="mt-5 rounded-xl bg-stone-50 p-4 text-sm text-stone-600">No activity recorded yet. Stamps and redemptions will appear here.</p>
            @else
                @include('merchant.customers.partials.activity-list', ['activityEvents' => $events->take(5)])
                @if($events->count() > 5)
                    <details class="mt-4 border-t border-stone-200 pt-4">
                        <summary class="cursor-pointer rounded text-sm font-semibold text-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600">Show {{ $events->count() - 5 }} more activities</summary>
                        @include('merchant.customers.partials.activity-list', ['activityEvents' => $events->slice(5)])
                    </details>
                @endif
            @endif
        </x-ui.card>
        <x-ui.card class="min-w-0 p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-stone-900">Customer card</h2>
            <p class="mt-2 text-sm leading-relaxed text-stone-500">Open the card or use its manual code if scanning is unavailable.</p>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div><p class="text-xs text-stone-500">Manual code</p><p class="mt-1 font-mono text-xl font-semibold tracking-widest text-stone-900">{{ $account->manual_entry_code ?? 'Not available' }}</p></div>
                <x-ui.button href="{{ route('card.show', $account->public_token) }}" variant="secondary" target="_blank" rel="noopener">Open card ↗</x-ui.button>
            </div>
            <details class="mt-5 border-t border-stone-200 pt-4">
                <summary class="cursor-pointer rounded text-sm font-semibold text-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600">Show card QR</summary>
                <div class="mt-4"><x-customer-design-preview :account="$account" /></div>
            </details>
        </x-ui.card>
    </div>

    <details class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6" id="customer-help">
        <summary class="cursor-pointer rounded text-base font-semibold text-stone-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600">Help with this card</summary>
        <div class="mt-5 space-y-6">
            @if($account->customer->email)
                <section>
                    <h2 class="text-base font-semibold text-stone-900">Send the card again</h2>
                    <p class="mt-2 text-sm text-stone-600">Resend the welcome email with a link to this customer's card.</p>
                    <form class="mt-3" method="POST" action="{{ route('merchant.customers.resend-welcome', $account) }}">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm">Resend welcome email</x-ui.button>
                    </form>
                </section>
            @endif
            <section class="border-t border-stone-200 pt-5">
                <h2 class="text-base font-semibold text-stone-900">Wallet updates</h2>
                <p class="mt-2 text-sm text-stone-600">{{ $walletStatus['active_apple_registrations'] === 0 ? 'No active Apple Wallet registration is recorded. The customer can still use their browser card. If they need help with Apple Wallet, ask them to open or add the pass.' : $walletStatus['next_action'] }}</p>
                <form class="mt-3" method="POST" action="{{ route('merchant.customers.sync-wallet', $account) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">Refresh Wallet card</x-ui.button>
                </form>
                <p class="mt-2 text-xs text-stone-500">Queues an update for this card. It may take a little time to appear in Wallet.</p>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-stone-500">Apple registrations</dt><dd class="mt-1 font-medium">{{ $walletStatus['active_apple_registrations'] }} active · {{ $walletStatus['total_apple_registrations'] }} total</dd></div>
                    <div><dt class="text-stone-500">Last Apple registration</dt><dd class="mt-1 font-medium">{{ $walletStatus['last_registered_at'] ? \Carbon\Carbon::parse($walletStatus['last_registered_at'])->format('M d, Y g:i A') : 'None yet' }}</dd></div>
                    <div><dt class="text-stone-500">Latest card change</dt><dd class="mt-1 font-medium">{{ $walletStatus['latest_card_change_at']->format('M d, Y g:i A') }}</dd></div>
                </dl>
            </section>
            <section class="border-t border-stone-200 pt-5">
                <h2 class="text-base font-semibold text-stone-900">Support history</h2>
                <p class="mt-1 text-sm text-stone-500">Email requests, Wallet updates and recent card activity.</p>
                @if($account->email_verification_sent_at)<p class="mt-2 text-xs text-stone-500">Verification sent {{ $account->email_verification_sent_at->diffForHumans() }}</p>@endif
                @if($supportTimeline->isEmpty())
                    <p class="mt-4 text-sm text-stone-500">No support events recorded yet.</p>
                @else
                    <ul class="mt-4 divide-y divide-stone-100">
                        @foreach($supportTimeline as $item)
                            <li class="flex flex-col justify-between gap-2 py-3 sm:flex-row">
                                <div><p class="text-sm font-semibold {{ $item['tone'] }}">{{ $item['title'] }}</p><p class="mt-1 text-sm text-stone-600">{{ $item['detail'] }}</p></div>
                                <p class="shrink-0 text-xs text-stone-500">{{ $item['at']?->format('M d, Y g:i A') }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </details>
</x-merchant-design-layout>
