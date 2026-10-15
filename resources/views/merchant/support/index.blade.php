<x-merchant-design-layout title="Help & support" subtitle="Quick answers and help for your café.">
    <x-slot:actions><x-ui.button href="mailto:support@kawhe.shop" variant="secondary">Contact support</x-ui.button></x-slot:actions>
    <section aria-label="Common tasks" class="grid gap-4 sm:grid-cols-3">
        <a href="{{ route('merchant.programs.index') }}" class="rounded-xl border border-stone-200 bg-white p-5 hover:bg-brand-50"><p class="font-semibold text-brand-700">Share your loyalty card →</p><p class="mt-2 text-sm text-stone-500">Find the join link and QR code for each card.</p></a>
        <a href="{{ route('merchant.staff.index') }}" class="rounded-xl border border-stone-200 bg-white p-5 hover:bg-brand-50"><p class="font-semibold text-brand-700">Give your team access →</p><p class="mt-2 text-sm text-stone-500">Invite staff with their own login and permissions.</p></a>
        <a href="{{ route('billing.index') }}" class="rounded-xl border border-stone-200 bg-white p-5 hover:bg-brand-50"><p class="font-semibold text-brand-700">Manage your plan →</p><p class="mt-2 text-sm text-stone-500">Review limits, update payment details or cancel.</p></a>
    </section>
    <div class="grid items-start gap-6 lg:grid-cols-2">
        <section aria-labelledby="common-questions" class="rounded-2xl border border-stone-200 bg-white p-6">
            <h2 id="common-questions" class="text-xl font-semibold">Common questions</h2>
            <dl class="mt-5 space-y-6 text-sm">
                @if(\App\Support\CoffeeCardSample::available())
                <div><dt class="font-semibold text-stone-900">Which card style should I choose?</dt><dd class="mt-2 text-stone-600">Simple uses one background colour and a fixed coffee-cup stamp. Illustrated adds a pattern you choose. Photo uses your uploaded image with an automatic readability overlay. Illustrated and Photo let you choose a stamp icon or upload your own.</dd></div>
                <div><dt class="font-semibold text-stone-900">Will my card look the same everywhere?</dt><dd class="mt-2 text-stone-600">Your browser card and Wallet cards share the selected style and progress. Apple and Google Wallet control their own layout and image cropping. Previews show sample progress; check a saved card before sharing. QR posters use your card’s logo, colours, reward and join link.</dd></div>
                @endif
                <div><dt class="font-semibold text-stone-900">How do customers join?</dt><dd class="mt-2 text-stone-600">Open Loyalty cards and choose Share join QR. Customers scan the QR code and fill in the join form. They don’t need to download an app.</dd></div>
                <div><dt class="font-semibold text-stone-900">What if a customer loses their card?</dt><dd class="mt-2 text-stone-600">Ask them to use the card recovery link on the join page and enter the email address they joined with.</dd></div>
                <div><dt class="font-semibold text-stone-900">How do I give staff access?</dt><dd class="mt-2 text-stone-600">Go to Team and invite them to the right store. Choose whether they can add stamps, redeem rewards, or both. Each person uses their own login.</dd></div>
                <div><dt class="font-semibold text-stone-900">Where can I change my plan?</dt><dd class="mt-2 text-stone-600">Open Billing to review your plan, payment details and cancellation options.</dd></div>
            </dl>
        </section>
        <section aria-labelledby="troubleshooting" class="rounded-2xl border border-stone-200 bg-white p-6">
            <h2 id="troubleshooting" class="text-xl font-semibold">Troubleshooting</h2>
            <dl class="mt-5 space-y-6 text-sm">
                <div><dt class="font-semibold text-stone-900">A card won’t scan</dt><dd class="mt-2 text-stone-600">Check camera access, improve the lighting and make sure the correct store is selected. You can also enter the card’s manual code.</dd></div>
                <div><dt class="font-semibold text-stone-900">Connection lost while stamping or redeeming</dt><dd class="mt-2 text-stone-600">Reconnect and use Retry safely if shown. Check the card’s latest balance before starting a new action.</dd></div>
                <div><dt class="font-semibold text-stone-900">A reward can’t be redeemed</dt><dd class="mt-2 text-stone-600">Check that the customer has verified their email and has an available reward. Make sure you’re in the correct store and have permission to redeem.</dd></div>
                <div><dt class="font-semibold text-stone-900">A Wallet card hasn’t updated</dt><dd class="mt-2 text-stone-600">Open the customer’s card in the browser to check their latest stamps. For more help, open Wallet help in your store settings.</dd></div>
                <div><dt class="font-semibold text-stone-900">An email hasn’t arrived</dt><dd class="mt-2 text-stone-600">Check the email address and junk folder. Business email users may also need to check quarantine with their email administrator.</dd></div>
            </dl>
        </section>
    </div>
    <section aria-labelledby="contact-support" class="flex flex-col gap-5 rounded-2xl border border-stone-200 bg-white p-6 sm:flex-row sm:items-center sm:justify-between">
        <div class="max-w-xl">
            <h2 id="contact-support" class="text-xl font-semibold">Still need help?</h2>
            <p class="mt-2 text-sm text-stone-600">Email support@kawhe.shop with your store name, what happened and roughly when. A screenshot can help. Please don’t include passwords.</p>
        </div>
        <x-ui.button href="mailto:support@kawhe.shop" class="shrink-0">Contact support</x-ui.button>
    </section>
    @php
        $summaryCards = [
            ['label' => 'Matching events', 'value' => number_format($summary['total'] ?? 0)],
            ['label' => 'Failed events', 'value' => number_format($summary['failed'] ?? 0)],
            ['label' => 'Needs attention', 'value' => number_format($summary['actionable'] ?? 0)],
        ];
    @endphp

    <details id="advanced-support-details" class="min-w-0 rounded-2xl border border-stone-200 bg-stone-50 p-4 sm:p-6" @if(request()->anyFilled(['store_id', 'event_type', 'status', 'q', 'page'])) open @endif>
        <summary class="cursor-pointer text-base font-semibold text-stone-900">Advanced details</summary>
        <p class="mt-2 text-sm text-stone-600">Technical event logs for investigating an issue with support.</p>
        <div class="mt-6 min-w-0 space-y-6">
        <section class="rounded-2xl border border-stone-200 bg-white p-6">
            <div class="space-y-4">
                <div>
                    <h2 class="text-xl font-semibold text-stone-900">Filter events</h2>
                    <p class="sr-only">Support activity across your stores</p>
                    <p class="mt-1 text-sm text-stone-600">Use this to trace wallet syncs, verification sends, billing issues, and manual support actions without leaving the app.</p>
                </div>
                <form method="GET" action="{{ route('merchant.support.index') }}" class="workspace-filterbar workspace-support-filters">
                    <div class="rounded-lg border border-stone-200 px-4 py-2">
                        <x-input-label for="support-store_id" value="Store" />
                        <x-ui.select id="support-store_id" name="store_id">
                            <option value="">All stores</option>
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}" {{ (string) $activeStoreId === (string) $store->id ? 'selected' : '' }}>{{ $store->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div class="rounded-lg border border-stone-200 px-4 py-2">
                        <x-input-label for="support-event_type" value="Event type" />
                        <x-ui.select id="support-event_type" name="event_type">
                            <option value="">All event types</option>
                            <option value="wallet_sync" {{ $eventType === 'wallet_sync' ? 'selected' : '' }}>Wallet sync</option>
                            <option value="verification_send" {{ $eventType === 'verification_send' ? 'selected' : '' }}>Verification send</option>
                            <option value="manual_support_action" {{ $eventType === 'manual_support_action' ? 'selected' : '' }}>Manual support action</option>
                            <option value="welcome_email_send" {{ $eventType === 'welcome_email_send' ? 'selected' : '' }}>Welcome email</option>
                            <option value="store_wallet_refresh" {{ $eventType === 'store_wallet_refresh' ? 'selected' : '' }}>Store wallet refresh</option>
                            <option value="billing_issue" {{ $eventType === 'billing_issue' ? 'selected' : '' }}>Billing issue</option>
                        </x-ui.select>
                    </div>
                    <div class="rounded-lg border border-stone-200 px-4 py-2">
                        <x-input-label for="support-status" value="Status" />
                        <x-ui.select id="support-status" name="status">
                            <option value="">All statuses</option>
                            <option value="success" {{ $status === 'success' ? 'selected' : '' }}>Success</option>
                            <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Partial</option>
                            <option value="blocked" {{ $status === 'blocked' ? 'selected' : '' }}>Blocked</option>
                            <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                        </x-ui.select>
                    </div>
                    <div class="rounded-lg border border-stone-200 px-4 py-2">
                        <x-input-label for="support-search" value="Search" />
                        <x-ui.input id="support-search" type="text" name="q" value="{{ $search }}" placeholder="Customer email, token, manual code" class="w-full" />
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <x-ui.button type="submit" variant="primary" >Apply filters</x-ui.button>
                        <x-ui.button href="{{ route('merchant.support.index') }}" variant="ghost" class="w-full sm:w-auto">Clear filters</x-ui.button>
                    </div>
                </form>
            </div>
        </section>

        <section class="min-w-0 space-y-4 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="text-xl font-semibold">Support events</h2>
            @if($logs->isEmpty())
                <div class="space-y-3 py-4">
                    <h3 class="text-xl font-semibold">No matching support events</h3>
                    <p class="text-base text-stone-600">Try another filter or clear your search.</p>
                    <x-ui.button href="{{ route('merchant.support.index') }}">Clear filters</x-ui.button>
                </div>
            @else
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Support event history">
            <table class="support-event-table w-full min-w-[960px] table-fixed text-left">
                <caption class="sr-only">Support events across your stores, newest first</caption>
                <x-ui.table-head>
                    <tr>
                        <x-ui.table-header-cell scope="col">Event</x-ui.table-header-cell>
                        <x-ui.table-header-cell scope="col">Store</x-ui.table-header-cell>
                        <x-ui.table-header-cell scope="col">Customer</x-ui.table-header-cell>
                        <x-ui.table-header-cell scope="col">Actor</x-ui.table-header-cell>
                        <x-ui.table-header-cell scope="col">Status</x-ui.table-header-cell>
                        <x-ui.table-header-cell scope="col">Time</x-ui.table-header-cell>
                    </tr>
                </x-ui.table-head>
                <x-ui.table-body>
                    @foreach($logs as $log)
                        <tr class="transition hover:bg-stone-50">
                            <x-ui.table-cell>
                                <div class="font-medium text-stone-900">{{ str($log->event_type)->replace('_', ' ')->title() }}</div>
                                <div class="mt-1 text-xs text-stone-500">{{ $log->message }}</div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->store->name ?? 'System' }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->loyaltyAccount?->customer?->email ?? 'N/A' }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->actor?->email ?? ($log->source ?? 'system') }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.badge :variant="$log->status === 'failed' ? 'danger' : (in_array($log->status, ['blocked', 'partial'], true) ? 'warning' : 'success')">
                                    {{ ucfirst($log->status) }}
                                </x-ui.badge>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->created_at->format('M d, Y g:i A') }}</x-ui.table-cell>
                        </tr>
                    @endforeach
                </x-ui.table-body>
            </table>
            </div>
            @endif
        </section>

        @if($logs->hasPages())<div>{{ $logs->links() }}</div>@endif

        <section aria-labelledby="support-summary" class="space-y-4 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 id="support-summary" class="text-xl font-semibold">Support summary</h2>
            <dl class="space-y-4 text-sm font-semibold text-stone-600">
                @foreach($summaryCards as $card)
                    <div class="flex flex-wrap gap-2"><dt>{{ $card['label'] }}</dt><dd>· {{ $card['value'] }}</dd></div>
                @endforeach
            </dl>
        </section>
        </div>
    </details>
</x-merchant-design-layout>
