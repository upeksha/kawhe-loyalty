<x-merchant-layout>
    <x-slot name="header">Store settings</x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-sm text-stone-500">
            <a href="{{ route('merchant.stores.index') }}" class="font-medium hover:text-brand-700">Stores</a>
            <span aria-hidden="true">/</span>
            <span class="text-stone-800" aria-current="page">{{ $store->name }}</span>
        </nav>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-brand-700">Store</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-stone-900 sm:text-3xl">{{ $store->name }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-stone-600">Manage this location and move to its loyalty cards when you need to change rewards, customer fields, colours, or Wallet images.</p>
            </div>
            @if(!$store->trashed() && $defaultProgram)
                <x-ui.button href="{{ route('merchant.stores.programs.edit', [$store, $defaultProgram]) }}" variant="primary">
                    Edit loyalty card
                </x-ui.button>
            @endif
        </div>

        @if($errors->any())
            <x-form-error-summary form-id="store-edit-form" />
        @endif

        @if($store->trashed())
            <x-ui.alert variant="warning">
                <p class="font-semibold">This store is archived</p>
                <p class="mt-1">New joins, QR sharing, stamping, and redemption are paused. Customer records and Wallet history are preserved.</p>
            </x-ui.alert>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="store-information-title">
                    <div class="border-b border-stone-100 pb-4">
                        <h2 id="store-information-title" class="text-lg font-semibold text-stone-900">Store information</h2>
                        <p class="mt-1 text-sm text-stone-600">This identifies the physical location or merchant workspace.</p>
                    </div>

                    <form id="store-edit-form" method="POST" action="{{ route('merchant.stores.update', $store) }}" class="mt-5 space-y-5">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="name" class="mb-1.5 block text-sm font-medium text-stone-800">Store name <span class="text-red-600" aria-hidden="true">*</span></label>
                            <x-ui.input id="name" name="name" type="text" value="{{ old('name', $store->name) }}" autocomplete="organization" required aria-describedby="name-help name-error" />
                            <p id="name-help" class="mt-1.5 text-xs text-stone-500">The location name merchants use to recognise this workspace.</p>
                            <x-input-error id="name-error" :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <label for="address" class="mb-1.5 block text-sm font-medium text-stone-800">Address <span class="font-normal text-stone-500">(optional)</span></label>
                            <x-ui.input id="address" name="address" type="text" value="{{ old('address', $store->address) }}" autocomplete="street-address" aria-describedby="address-help address-error" />
                            <p id="address-help" class="mt-1.5 text-xs text-stone-500">Useful when you manage more than one location.</p>
                            <x-input-error id="address-error" :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <div class="flex justify-end">
                            <x-ui.button type="submit" variant="primary" loading-text="Saving…">Save store</x-ui.button>
                        </div>
                    </form>
                </section>

                <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="loyalty-cards-title">
                    <div class="flex flex-col gap-4 border-b border-stone-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 id="loyalty-cards-title" class="text-lg font-semibold text-stone-900">Loyalty cards</h2>
                            <p class="mt-1 max-w-2xl text-sm text-stone-600">Reward settings, customer fields, colours and Wallet images are managed on the loyalty card.</p>
                        </div>
                        <x-ui.button href="{{ route('merchant.stores.programs.index', $store) }}" variant="secondary" size="sm">View all cards</x-ui.button>
                    </div>

                    @if($defaultProgram)
                        <div class="mt-5 flex flex-col gap-5 rounded-2xl bg-stone-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate font-semibold text-stone-900">{{ $defaultProgram->name }}</h3>
                                    <x-ui.badge variant="success">Default card</x-ui.badge>
                                    @if($defaultProgram->trashed())
                                        <x-ui.badge variant="warning">Archived</x-ui.badge>
                                    @endif
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                                    <div><dt class="text-stone-500">Reward</dt><dd class="font-medium text-stone-900">{{ $defaultProgram->reward_target }} stamps</dd></div>
                                    <div><dt class="text-stone-500">Benefit</dt><dd class="font-medium text-stone-900">{{ $defaultProgram->reward_title }}</dd></div>
                                    <div><dt class="text-stone-500">Customers</dt><dd class="font-medium text-stone-900">{{ number_format($defaultProgram->loyalty_accounts_count ?? 0) }}</dd></div>
                                </dl>
                            </div>
                            <div class="flex flex-col gap-2 sm:items-end">
                                <x-ui.button href="{{ route('merchant.stores.programs.edit', [$store, $defaultProgram]) }}" variant="primary" size="sm">Edit loyalty card</x-ui.button>
                                <a href="{{ route('merchant.stores.programs.qr', [$store, $defaultProgram]) }}" class="inline-flex min-h-11 items-center justify-center text-sm font-semibold text-brand-700 hover:text-brand-800">View card QR</a>
                            </div>
                        </div>
                    @else
                        <x-ui.empty-state class="mt-5" heading="No loyalty card found" description="Create a loyalty card before sharing a join QR." />
                    @endif
                </section>

                <details class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6">
                    <summary class="cursor-pointer font-semibold text-stone-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">Legacy fallback settings</summary>
                    <p class="mt-3 text-sm leading-6 text-stone-600">These stored values support legacy Store join links and onboarding compatibility. Card settings remain the customer-facing source of truth and should be edited from the loyalty card.</p>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-stone-500">Fallback reward</dt><dd class="font-medium text-stone-900">{{ $store->reward_target }} stamps for {{ $store->reward_title }}</dd></div>
                        <div><dt class="text-stone-500">Fallback colours</dt><dd class="mt-1 flex items-center gap-2 font-medium text-stone-900"><span class="h-5 w-5 rounded-full border border-stone-300" style="background: {{ $store->brand_color }}"></span><span class="h-5 w-5 rounded-full border border-stone-300" style="background: {{ $store->background_color }}"></span></dd></div>
                    </dl>
                </details>

                <section class="rounded-2xl border border-red-200 bg-red-50 p-5 sm:p-6" aria-labelledby="archive-store-title">
                    <h2 id="archive-store-title" class="font-semibold text-red-900">{{ $store->trashed() ? 'Restore store' : 'Archive store' }}</h2>
                    <p class="mt-2 text-sm leading-6 text-red-800">{{ $store->trashed() ? 'Restoring re-enables join links, QR sharing, stamping, and redemption.' : 'Archiving pauses new joins and store activity. Existing customers, passes, and history remain preserved.' }}</p>
                    <form method="POST" action="{{ $store->trashed() ? route('merchant.stores.restore', $store) : route('merchant.stores.destroy', $store) }}" class="mt-4" @unless($store->trashed()) onsubmit="return confirm('Archive this store? New joins, stamping, and redemption will pause. Customer history will remain preserved.');" @endunless>
                        @csrf
                        @unless($store->trashed()) @method('DELETE') @endunless
                        <x-ui.button type="submit" :variant="$store->trashed() ? 'secondary' : 'danger'" :loading-text="$store->trashed() ? 'Restoring…' : 'Archiving…'">{{ $store->trashed() ? 'Restore store' : 'Archive store' }}</x-ui.button>
                    </form>
                </section>
            </div>

            <aside class="space-y-4" aria-label="Store status">
                <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div><h2 class="font-semibold text-stone-900">Store readiness</h2><p class="mt-1 text-xs text-stone-500">Operational status for this location.</p></div>
                        <x-ui.status-icon-badge :icon="$walletHealth['status_icon']" :label="$walletHealth['status_label']" :tone="$walletHealth['status_tone']" />
                    </div>
                    <p class="mt-4 text-sm leading-6 text-stone-600">{{ $walletHealth['recommended_action'] }}</p>
                    <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-stone-100 pt-4 text-sm">
                        <div><dt class="text-stone-500">Customer cards</dt><dd class="mt-1 font-semibold text-stone-900">{{ number_format($walletHealth['active_cards']) }}</dd></div>
                        <div><dt class="text-stone-500">Apple installs</dt><dd class="mt-1 font-semibold text-stone-900">{{ number_format($walletHealth['active_apple_registrations']) }}</dd></div>
                    </dl>
                </section>

                @if(!$store->trashed())
                    <section class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
                        <h2 class="font-semibold text-stone-900">Store links</h2>
                        <div class="mt-3 grid gap-2">
                            <a href="{{ route('merchant.stores.qr', $store) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-700 hover:text-brand-800">Open store QR</a>
                            <a href="{{ route('merchant.stores.programs.index', $store) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-700 hover:text-brand-800">Manage loyalty cards</a>
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-merchant-layout>
