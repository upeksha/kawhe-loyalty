<x-merchant-design-layout title="Help & support" subtitle="Find your next step, get help, or check a delivery issue.">
    <x-slot:actions><x-ui.button href="mailto:support@kawhe.shop" variant="secondary">Contact support</x-ui.button></x-slot:actions>
    <section aria-label="Common tasks" class="grid gap-4 sm:grid-cols-3">
        <a href="{{ route('merchant.programs.index') }}" class="rounded-xl border border-stone-200 bg-white p-5 hover:bg-brand-50"><p class="font-semibold text-brand-700">Share your loyalty card →</p><p class="mt-2 text-sm text-stone-500">Find the join link and QR code for each card.</p></a>
        <a href="{{ route('merchant.staff.index') }}" class="rounded-xl border border-stone-200 bg-white p-5 hover:bg-brand-50"><p class="font-semibold text-brand-700">Give your team access →</p><p class="mt-2 text-sm text-stone-500">Invite staff with their own login and permissions.</p></a>
        <a href="{{ route('billing.index') }}" class="rounded-xl border border-stone-200 bg-white p-5 hover:bg-brand-50"><p class="font-semibold text-brand-700">Manage your plan →</p><p class="mt-2 text-sm text-stone-500">Review limits, update payment details or cancel.</p></a>
    </section>
    @php
        $summaryCards = [
            ['label' => 'Matching events', 'value' => number_format($summary['total'] ?? 0)],
            ['label' => 'Failed events', 'value' => number_format($summary['failed'] ?? 0)],
            ['label' => 'Needs attention', 'value' => number_format($summary['actionable'] ?? 0)],
        ];
    @endphp

    <div class="space-y-6">
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
</x-merchant-design-layout>
