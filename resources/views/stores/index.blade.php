<x-merchant-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <span>{{ __('My Stores') }}</span>
            <x-ui.button href="{{ route('merchant.stores.create') }}" variant="primary" size="sm" class="ml-5">
                Add Store
            </x-ui.button>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if($stores->isEmpty() && ($archivedStores ?? collect())->isEmpty())
            <x-ui.card class="p-12 text-center">
                <h3 class="text-lg font-semibold text-stone-900">No stores yet</h3>
                <p class="text-stone-500 mt-2">Create your first store to generate a join QR code and start collecting customers.</p>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <x-ui.button href="{{ route('merchant.stores.create') }}" variant="primary">
                        Create Your First Store
                    </x-ui.button>
                    <x-ui.button href="{{ route('merchant.dashboard') }}" variant="secondary">
                        Back to Dashboard
                    </x-ui.button>
                </div>
            </x-ui.card>
        @else
            @if($stores->isNotEmpty())
                <div>
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Active Stores</h2>
                        <span class="text-xs text-stone-500">{{ $stores->count() }} active</span>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:hidden">
                        @foreach($stores as $store)
                            <x-ui.card class="p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold text-stone-900">{{ $store->name }}</h3>
                                        <p class="text-sm text-stone-500 mt-1">{{ $store->address ?? 'No address set' }}</p>
                                        <p class="text-sm text-stone-600 mt-2">{{ $store->reward_target }} stamps for {{ $store->reward_title }}</p>
                                    </div>
                                </div>
                                <div class="mt-4 flex items-center gap-3">
                                    <a href="{{ route('merchant.stores.edit', $store) }}" class="text-brand-600 hover:text-brand-700 font-medium text-sm">Edit</a>
                                    <a href="{{ route('merchant.stores.qr', $store) }}" class="text-brand-600 hover:text-brand-700 font-medium text-sm">QR Code</a>
                                </div>
                            </x-ui.card>
                        @endforeach
                    </div>

                    <x-ui.card class="p-0 overflow-hidden hidden md:block">
                        <x-ui.table>
                            <x-ui.table-head>
                                <tr>
                                    <x-ui.table-header-cell>Store Name</x-ui.table-header-cell>
                                    <x-ui.table-header-cell>Address</x-ui.table-header-cell>
                                    <x-ui.table-header-cell>Reward Target</x-ui.table-header-cell>
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
                                        <x-ui.table-cell>
                                            {{ $store->reward_target }} stamps for {{ $store->reward_title }}
                                        </x-ui.table-cell>
                                        <x-ui.table-cell class="text-right">
                                            <div class="flex justify-end gap-3">
                                                <a href="{{ route('merchant.stores.edit', $store) }}" class="text-brand-600 hover:text-brand-700 font-medium text-sm">Edit</a>
                                                <a href="{{ route('merchant.stores.qr', $store) }}" class="text-brand-600 hover:text-brand-700 font-medium text-sm">QR Code</a>
                                            </div>
                                        </x-ui.table-cell>
                                    </tr>
                                @endforeach
                            </x-ui.table-body>
                        </x-ui.table>
                    </x-ui.card>
                </div>
            @endif

            @if(($archivedStores ?? collect())->isNotEmpty())
                <x-ui.card class="p-5">
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
</x-merchant-layout>
