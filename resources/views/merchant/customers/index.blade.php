<x-merchant-design-layout title="Your regulars" subtitle="Find a customer and open their loyalty card.">
    <form method="GET" action="{{ route('merchant.customers.index') }}" class="max-w-[620px] space-y-3">
        <div>
            <label for="customer-search" class="block text-sm font-semibold">Search customers</label>
            <x-ui.input id="customer-search" name="q" value="{{ $q }}" placeholder="Name, email, phone, manual code or public token" class="mt-2" />
        </div>
        @if($activeStoreId)<input type="hidden" name="store_id" value="{{ $activeStoreId }}">@endif
        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" data-loading-text="Searching…">Search</x-ui.button>
            @if($q)<x-ui.button href="{{ route('merchant.customers.index', ['store_id' => $activeStoreId]) }}" variant="secondary">Clear</x-ui.button>@endif
        </div>
    </form>
    @if($accounts->isEmpty())
            <x-ui.empty-state
                :heading="$q || $activeStoreId ? 'No matching customers' : 'No customers yet'"
                :description="$q || $activeStoreId ? 'Try a different search term or clear your filters.' : 'Share your store join link or QR code and customers will appear here automatically.'"
            >
                @if($q || $activeStoreId)
                    <div class="flex flex-wrap justify-center gap-2">
                        <x-ui.button href="{{ route('merchant.customers.index') }}" variant="primary" size="sm">
                            Clear Filters
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.stores.index') }}" variant="secondary" size="sm">
                            View Store QR
                        </x-ui.button>
                    </div>
                @else
                    <div class="flex flex-wrap justify-center gap-2">
                        <x-ui.button href="{{ route('merchant.stores.index') }}" variant="primary" size="sm">
                            View Store QR
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.scanner') }}" variant="secondary" size="sm">
                            Open Scanner
                        </x-ui.button>
                    </div>
                @endif
            </x-ui.empty-state>
    @else
        <x-ui.card class="dashboard-panel p-6">
            <div class="hidden grid-cols-4 gap-4 pb-4 text-sm font-semibold text-stone-600 md:grid" aria-hidden="true">
                <span>Customer</span><span>Card</span><span>Progress</span><span>Rewards</span>
            </div>
            <ul class="divide-y divide-stone-200">
                @foreach($accounts as $account)
                    <li class="space-y-4 py-4 first:pt-0">
                        <div class="grid gap-4 md:grid-cols-4">
                            <div class="min-w-0">
                                <a href="{{ route('merchant.customers.show', [$account, ...request()->only(['q', 'store_id'])]) }}" class="font-semibold text-brand-700 underline underline-offset-4">{{ $account->customer->name ?? '(No name)' }}</a>
                                <p class="mt-1 break-words text-sm text-stone-600">{{ $account->customer->email ?? '-' }}</p>
                            </div>
                            <div><span class="text-sm text-stone-500 md:hidden">Card: </span>{{ $account->program_name }}<p class="mt-1 text-sm text-stone-600">{{ $account->store->name }}</p></div>
                            <p><span class="text-sm text-stone-500 md:hidden">Progress: </span>{{ $account->stamp_count }} / {{ $account->reward_target }}</p>
                            <div><p>{{ $account->reward_balance }} available</p>
                                @if($account->reward_redeemed_at)<p class="mt-1 text-xs text-stone-500">Last redeemed {{ $account->reward_redeemed_at->format('M d, Y') }}</p>@endif
                            </div>
                        </div>
                        <details class="text-sm">
                            <summary class="cursor-pointer py-2 font-medium text-brand-700">Contact and card details</summary>
                            <dl class="mt-2 grid gap-3 rounded-lg bg-stone-50 p-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div><dt class="text-stone-500">Phone</dt><dd>{{ $account->customer->phone ?? '-' }}</dd></div>
                                <div><dt class="text-stone-500">Birthday</dt><dd>{{ $account->customer->birthday ? \Carbon\Carbon::parse($account->customer->birthday)->format('M d, Y') : '-' }}</dd></div>
                                <div><dt class="text-stone-500">Manual code</dt><dd class="font-mono">{{ $account->manual_entry_code ?? '-' }}</dd></div>
                                <div><dt class="text-stone-500">Joined</dt><dd>{{ $account->created_at->format('M d, Y') }}</dd></div>
                                <div><dt class="text-stone-500">Last stamped</dt><dd>{{ $account->last_stamped_at?->format('M d, Y') ?? '-' }}</dd></div>
                                <div><dt class="text-stone-500">Full name</dt><dd>{{ trim(($account->customer->first_name ?? '') . ' ' . ($account->customer->last_name ?? '')) ?: ($account->customer->name ?? '-') }}</dd></div>
                            </dl>
                        </details>
                        <x-ui.button href="{{ route('merchant.customers.show', [$account, ...request()->only(['q', 'store_id'])]) }}" variant="secondary" class="w-full max-w-[300px]">View details</x-ui.button>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
        {{ $accounts->links() }}
    @endif
    <form method="GET" action="{{ route('merchant.customers.index') }}" class="max-w-[620px] space-y-3">
        @if($q)<input type="hidden" name="q" value="{{ $q }}">@endif
        <div class="rounded-lg border border-stone-200 bg-white px-4 py-2">
            <label for="customer-store" class="block text-sm font-semibold">Store filter</label>
            <x-ui.select id="customer-store" name="store_id" x-data @change="$el.form.requestSubmit()" class="mt-2 w-full"><option value="">All Stores</option>
                @foreach($stores as $store)<option value="{{ $store->id }}" @selected($activeStoreId == $store->id)>{{ $store->name }}</option>@endforeach
            </x-ui.select>
        </div>
        <x-ui.button type="submit" variant="secondary">Apply store filter</x-ui.button>
    </form>
    <div class="max-w-[400px] space-y-3">
        @if($canExportCustomers)
            <x-ui.button href="{{ route('merchant.customers.export', request()->only(['store_id', 'q'])) }}" variant="secondary" class="w-full">Export CSV</x-ui.button>
        @else
            <x-ui.button href="{{ route('billing.index') }}" variant="secondary" class="w-full">Export CSV (Pro)</x-ui.button>
            <p class="text-sm text-stone-500">Upgrade to Pro to export customer details.</p>
        @endif
    </div>
    <p class="text-sm text-stone-600">For customer support lookup, ask for the manual code or email to find the right card quickly.</p>
</x-merchant-design-layout>
