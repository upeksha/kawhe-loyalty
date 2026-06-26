<x-merchant-layout>
    <x-slot name="header">
        <x-ui.page-header title="{{ __('Loyalty Cards') }}" />
    </x-slot>

    @php
        $totalPrograms = $storesWithPrograms->sum(fn ($store) => $store->loyaltyPrograms->count());
        $showAllStores = $storesWithPrograms->count() > 1;
    @endphp

    <div class="space-y-8">
        @if($totalPrograms === 0)
            <x-ui.empty-state
                heading="No loyalty cards yet"
                description="{{ ($usageStats['can_create_program'] ?? false) ? 'Create your first loyalty card to get a join link and QR code for customers.' : 'Your plan limit for loyalty cards has been reached. Upgrade to add more cards.' }}"
            >
                @if($usageStats['can_create_program'] ?? false)
                    @php
                        $firstStore = $storesWithPrograms->first(fn ($store) => ! $store->trashed());
                    @endphp
                    @if($firstStore)
                        <x-ui.button href="{{ route('merchant.stores.programs.create', $firstStore) }}" variant="primary">
                            Add Loyalty Card
                        </x-ui.button>
                    @endif
                @else
                    <x-ui.button href="{{ route('billing.index') }}" variant="primary">
                        View plans
                    </x-ui.button>
                @endif
            </x-ui.empty-state>
        @else
            @if($showAllStores)
                <p class="text-sm text-stone-600">
                    Cards are grouped by store. Each store can run its own set of loyalty cards.
                </p>
            @endif

            @foreach($storesWithPrograms as $store)
                @include('programs.partials.store-section', ['store' => $store, 'usageService' => $usageService])
            @endforeach
        @endif
    </div>
</x-merchant-layout>
