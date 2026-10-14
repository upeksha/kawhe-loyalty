<x-merchant-design-layout title="Stores" subtitle="Manage your locations and their loyalty cards.">
    <div class="space-y-6">
        @if($stores->isEmpty() && ($archivedStores ?? collect())->isEmpty())
            <x-ui.empty-state
                heading="No active stores"
                description="Use the setup wizard for your first store. Come back here later to manage stores and add extra locations."
            >
                <div class="flex flex-wrap justify-center gap-2">
                    <x-ui.button href="{{ route('merchant.onboarding.wizard.store-basics') }}" variant="primary">
                        Open Setup Wizard
                    </x-ui.button>
                    <x-ui.button href="{{ route('merchant.dashboard') }}" variant="secondary">
                        Back to Dashboard
                    </x-ui.button>
                </div>
            </x-ui.empty-state>
        @else
            @if($stores->isNotEmpty())
                <x-ui.card class="dashboard-panel p-6">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="text-xl font-semibold text-stone-900">Active Stores</h2>
                        <span class="text-xs text-stone-500">{{ $stores->count() }} active</span>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:hidden">
                        @foreach($stores as $store)
                            <x-ui.card class="p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold text-stone-900">{{ $store->name }}</h3>
                                        <p class="text-sm text-stone-500 mt-1">{{ $store->address ?? 'No address set' }}</p>
                                        <p class="mt-2 text-sm text-stone-600">{{ $store->loyalty_programs_count }} active card(s)</p>
                                        <p class="text-sm text-stone-600 mt-2">{{ $store->reward_target }} stamps for {{ $store->reward_title }}</p>
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <a href="{{ route('merchant.stores.edit', $store) }}" class="text-brand-600 hover:text-brand-700 font-medium text-sm">Store settings</a>
                                    <a href="{{ route('merchant.programs.index') }}#store-{{ $store->id }}" class="text-brand-600 hover:text-brand-700 font-medium text-sm">Loyalty Cards</a>
                                    <a href="{{ route('merchant.stores.qr', $store) }}" class="text-sm font-semibold text-brand-700">QR &amp; poster</a>
                                </div>
                            </x-ui.card>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <x-ui.table>
                            <x-ui.table-head>
                                <tr>
                                    <x-ui.table-header-cell>Store Name</x-ui.table-header-cell>
                                    <x-ui.table-header-cell>Address</x-ui.table-header-cell>
                                    <x-ui.table-header-cell>Cards</x-ui.table-header-cell>
                                    <x-ui.table-header-cell>Default Card</x-ui.table-header-cell>
                                    <x-ui.table-header-cell class="text-right">Actions</x-ui.table-header-cell>
                                </tr>
                            </x-ui.table-head>
                            <x-ui.table-body>
                                @foreach($stores as $store)
                                    <tr class="hover:bg-stone-50 transition-colors">
                                        <x-ui.table-cell class="font-medium text-stone-900">
                                            {{ $store->name }}
                                        </x-ui.table-cell>
                                        <x-ui.table-cell>
                                            {{ $store->address ?? 'N/A' }}
                                        </x-ui.table-cell>
                                        <x-ui.table-cell>{{ $store->loyalty_programs_count }}</x-ui.table-cell>
                                        <x-ui.table-cell>
                                            {{ $store->reward_target }} stamps for {{ $store->reward_title }}
                                        </x-ui.table-cell>
                                        <x-ui.table-cell class="text-right">
                                            <div class="flex flex-wrap justify-end gap-3">
                                                <a href="{{ route('merchant.stores.edit', $store) }}" class="text-brand-600 hover:text-brand-700 font-medium text-sm">Store settings</a>
                                                <a href="{{ route('merchant.programs.index') }}#store-{{ $store->id }}" class="text-brand-600 hover:text-brand-700 font-medium text-sm">Loyalty Cards</a>
                                    <a href="{{ route('merchant.stores.qr', $store) }}" class="text-sm font-semibold text-brand-700">QR &amp; poster</a>
                                            </div>
                                        </x-ui.table-cell>
                                    </tr>
                                @endforeach
                            </x-ui.table-body>
                        </x-ui.table>
                    </div>
                    <div class="mt-6 grid max-w-[400px] gap-4">
                        @if($usageStats['can_create_store'] ?? false)
                            <x-ui.button href="{{ route('merchant.stores.create') }}" class="dashboard-button">Add Another Store</x-ui.button>
                        @else
                            <p class="text-sm text-stone-600">Store limit reached. Your existing stores remain available.</p>
                            <x-ui.button href="{{ route('billing.index') }}" variant="secondary" class="dashboard-button">View plans</x-ui.button>
                        @endif
                        @if(($archivedStores ?? collect())->isNotEmpty())
                            <x-ui.button href="#archived-stores" variant="ghost" class="dashboard-button">View archived stores</x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            @endif

            @if($stores->isEmpty() && ($usageStats['can_create_store'] ?? false))
                <x-ui.button href="{{ route('merchant.stores.create') }}" class="dashboard-button w-full max-w-[400px]">Add Another Store</x-ui.button>
            @endif

            @if(($archivedStores ?? collect())->isNotEmpty())
                <x-ui.card id="archived-stores" class="dashboard-panel p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-bold text-stone-900">Archived Stores</h3>
                            <p class="mt-1 text-sm text-stone-600">Archived stores keep their customers and history, but joins, QR sharing, and wallet usage are paused until you restore them.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">
                            {{ $archivedStores->count() }} archived
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @foreach($archivedStores as $store)
                            <div class="rounded-2xl border border-stone-200 bg-stone-50/70 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="font-semibold text-stone-900">{{ $store->name }}</p>
                                        <p class="mt-1 text-sm text-stone-500">
                                            Archived {{ $store->deleted_at?->diffForHumans() }}. Customers and wallet history are preserved.
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui.button href="{{ route('merchant.stores.edit', $store) }}" variant="ghost" size="sm">
                                            Review
                                        </x-ui.button>
                                        <form method="POST" action="{{ route('merchant.stores.restore', $store) }}">
                                            @csrf
                                            <x-ui.button type="submit" variant="secondary" size="sm">
                                                Restore Store
                                            </x-ui.button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif
        @endif
    </div>
</x-merchant-design-layout>
