@props([
    'store',
    'usageService',
])

@php
    $programs = $store->loyaltyPrograms;
    $activePrograms = $programs->whereNull('deleted_at')->values();
    $archivedPrograms = $programs->whereNotNull('deleted_at')->values();
    $canCreateProgram = $usageService->canCreateProgramForStore(auth()->user(), $store);
@endphp

<section class="space-y-4" id="store-{{ $store->id }}">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-stone-900">{{ $store->name }}</h2>
                @if($store->trashed())
                    <span class="inline-flex rounded-full bg-stone-100 px-2.5 py-1 text-xs font-semibold text-stone-600">Archived store</span>
                @endif
            </div>
            @if($store->address)
                <p class="mt-1 text-sm text-stone-500">{{ $store->address }}</p>
            @endif
        </div>
        @if($canCreateProgram && ! $store->trashed())
            <x-ui.button href="{{ route('merchant.stores.programs.create', $store) }}" variant="secondary" size="sm">
                Add card to this store
            </x-ui.button>
        @endif
    </div>

    @if($activePrograms->isEmpty() && $archivedPrograms->isEmpty())
        <x-ui.card class="p-5">
            <p class="text-sm text-stone-600">
                No loyalty cards for this store yet.
                @if($canCreateProgram && ! $store->trashed())
                    <a href="{{ route('merchant.stores.programs.create', $store) }}" class="font-medium text-brand-600 hover:text-brand-700">Create the first card</a>.
                @endif
            </p>
        </x-ui.card>
    @else
        @if($activePrograms->isNotEmpty())
            <div>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Active Cards</h3>
                    <span class="text-xs text-stone-500">{{ $activePrograms->count() }} active</span>
                </div>

                <div class="grid grid-cols-1 gap-4 md:hidden">
                    @foreach($activePrograms as $program)
                        <x-ui.card class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-semibold text-stone-900">{{ $program->name }}</h4>
                                        @if($program->is_default)
                                            <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">Default</span>
                                        @endif
                                    </div>
                                    <p class="mt-2 text-sm text-stone-600">{{ $program->reward_target }} stamps for {{ $program->reward_title }}</p>
                                    <p class="mt-1 text-sm text-stone-500">{{ $program->loyalty_accounts_count }} customer cards joined</p>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center gap-3">
                                <a href="{{ route('merchant.stores.programs.qr', [$store, $program]) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">QR Code</a>
                                <a href="{{ route('merchant.stores.programs.edit', [$store, $program]) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Edit</a>
                                @if(! $program->is_default)
                                    <form method="POST" action="{{ route('merchant.stores.programs.destroy', [$store, $program]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-brand-600 hover:text-brand-700">Archive</button>
                                    </form>
                                @endif
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>

                <x-ui.card class="hidden overflow-hidden p-0 md:block">
                    <x-ui.table>
                        <x-ui.table-head>
                            <tr>
                                <x-ui.table-header-cell>Card Name</x-ui.table-header-cell>
                                <x-ui.table-header-cell>Reward</x-ui.table-header-cell>
                                <x-ui.table-header-cell>Customer Cards</x-ui.table-header-cell>
                                <x-ui.table-header-cell class="text-right">Actions</x-ui.table-header-cell>
                            </tr>
                        </x-ui.table-head>
                        <x-ui.table-body>
                            @foreach($activePrograms as $program)
                                <tr class="transition-colors hover:bg-stone-50">
                                    <x-ui.table-cell class="font-medium text-stone-900">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $program->name }}</span>
                                            @if($program->is_default)
                                                <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">Default</span>
                                            @endif
                                        </div>
                                    </x-ui.table-cell>
                                    <x-ui.table-cell>
                                        {{ $program->reward_target }} stamps for {{ $program->reward_title }}
                                    </x-ui.table-cell>
                                    <x-ui.table-cell>
                                        {{ $program->loyalty_accounts_count }}
                                    </x-ui.table-cell>
                                    <x-ui.table-cell class="text-right">
                                        <div class="flex justify-end gap-3">
                                            <a href="{{ route('merchant.stores.programs.qr', [$store, $program]) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">QR Code</a>
                                            <a href="{{ route('merchant.stores.programs.edit', [$store, $program]) }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Edit</a>
                                            @if(! $program->is_default)
                                                <form method="POST" action="{{ route('merchant.stores.programs.destroy', [$store, $program]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-sm font-medium text-brand-600 hover:text-brand-700">Archive</button>
                                                </form>
                                            @endif
                                        </div>
                                    </x-ui.table-cell>
                                </tr>
                            @endforeach
                        </x-ui.table-body>
                    </x-ui.table>
                </x-ui.card>
            </div>
        @endif

        @if($archivedPrograms->isNotEmpty())
            <x-ui.card class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-stone-900">Archived Cards</h3>
                        <p class="mt-1 text-sm text-stone-600">Archived cards keep their customer history, but new joins and scanning stay paused until you restore them.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">
                        {{ $archivedPrograms->count() }} archived
                    </span>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach($archivedPrograms as $program)
                        <div class="rounded-2xl border border-stone-200 bg-stone-50/70 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-semibold text-stone-900">{{ $program->name }}</p>
                                    <p class="mt-1 text-sm text-stone-500">
                                        Archived {{ $program->deleted_at?->diffForHumans() }}. Existing customer progress is preserved.
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-ui.button href="{{ route('merchant.stores.programs.edit', [$store, $program]) }}" variant="ghost" size="sm">
                                        Review
                                    </x-ui.button>
                                    <form method="POST" action="{{ route('merchant.stores.programs.restore', [$store, $program->id]) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="secondary" size="sm">
                                            Restore Card
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
</section>
