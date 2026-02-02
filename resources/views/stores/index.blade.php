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
        @if($stores->isEmpty())
            <x-ui.card class="p-12 text-center">
                <p class="text-stone-500 mb-4">You haven't created any stores yet.</p>
                <x-ui.button href="{{ route('merchant.stores.create') }}" variant="primary">
                    Create Your First Store
                </x-ui.button>
            </x-ui.card>
        @else
            <x-ui.card class="p-0 overflow-hidden">
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
        @endif
    </div>
</x-merchant-layout>

