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
        <x-ui.empty-state heading="No loyalty cards for this store yet" description="Create a card to choose a reward and get a join QR for customers.">
            @if($canCreateProgram && ! $store->trashed())
                <x-ui.button href="{{ route('merchant.stores.programs.create', $store) }}">Create the first card</x-ui.button>
            @else
                <x-ui.button href="{{ $store->trashed() ? route('merchant.stores.index') : route('billing.index') }}">{{ $store->trashed() ? 'View stores' : 'View plans' }}</x-ui.button>
            @endif
        </x-ui.empty-state>
    @else
        @if($activePrograms->isNotEmpty())
            <div>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Active Cards</h3>
                    <span class="text-xs text-stone-500">{{ $activePrograms->count() }} active</span>
                </div>

                <div class="space-y-6">
                    @foreach($activePrograms as $program)
                        <x-ui.card class="dashboard-panel max-w-5xl overflow-hidden !p-0">
                            <div class="grid md:grid-cols-[260px_minmax(0,1fr)]">
                                <div class="flex items-center justify-center border-b border-stone-100 bg-stone-50 p-5 md:border-b-0 md:border-r">
                                    <x-loyalty-design-preview :store="$store" :program="$program" />
                                </div>
                                <div class="flex min-w-0 flex-col gap-4 p-5 sm:p-6">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="min-w-0 break-words text-xl font-semibold text-stone-900">{{ $program->name }}</h4>
                                        @if($program->is_default)<x-ui.badge variant="success">Default</x-ui.badge>@endif
                                        <x-ui.badge :variant="$store->trashed() ? 'warning' : 'success'">{{ $store->trashed() ? 'Store paused' : 'Active card' }}</x-ui.badge>
                                    </div>
                                    @if(trim($program->reward_title) !== trim($program->name))
                                        <p class="text-sm text-stone-600">Reward: <span class="font-semibold text-stone-900">{{ $program->reward_title }}</span></p>
                                    @endif
                                    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 border-y border-stone-100 py-4">
                                        <div class="min-w-0">
                                            <dt class="text-xs font-medium text-stone-500">Stamps to earn</dt>
                                            <dd class="mt-1 text-sm font-semibold text-stone-900">{{ $program->reward_target }}</dd>
                                        </div>
                                        <div class="min-w-0">
                                            <dt class="text-xs font-medium text-stone-500">Customers joined</dt>
                                            <dd class="mt-1 text-sm font-semibold text-stone-900">{{ number_format($program->loyalty_accounts_count) }}</dd>
                                        </div>
                                    </dl>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                        <x-ui.button href="{{ route('merchant.stores.programs.edit', [$store, $program]) }}" class="dashboard-button">Edit card appearance</x-ui.button>
                                        <x-ui.button href="{{ route('merchant.stores.programs.qr', [$store, $program]) }}" variant="secondary" class="dashboard-button">Share join QR</x-ui.button>
                                        @if(! $program->is_default)
                                            <form method="POST" action="{{ route('merchant.stores.programs.destroy', [$store, $program]) }}" onsubmit="return confirm('Archive this card? New joins and scanning will pause. Customer history will remain preserved.');">
                                                @csrf @method('DELETE')
                                                <x-ui.button type="submit" variant="danger" class="dashboard-button w-full">Archive</x-ui.button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>
            </div>
        @endif

        @if($archivedPrograms->isNotEmpty())
            <x-ui.card class="dashboard-panel p-6">
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
