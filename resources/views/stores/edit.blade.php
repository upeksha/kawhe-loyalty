<x-merchant-design-layout title="Store settings" :subtitle="$store->name">

    <x-slot:actions>
        <x-ui.button href="{{ route('merchant.stores.index') }}" variant="ghost">← Back to stores</x-ui.button>
        @if(!$store->trashed())<x-ui.button href="{{ route('merchant.stores.qr', $store) }}" variant="secondary">QR & poster</x-ui.button>@endif
    </x-slot:actions>
    <div class="min-w-0 space-y-6">
        <div class="flex flex-wrap items-center gap-3 text-sm text-stone-500">
            <x-ui.badge :variant="$store->trashed() ? 'warning' : 'success'">{{ $store->trashed() ? 'Archived' : 'Active' }}</x-ui.badge>
            <span>{{ $store->trashed() ? 'Store activity is paused.' : 'Manage loyalty cards and customer activity for this location.' }}</span>
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

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="store-information-title">
                    <div class="border-b border-stone-100 pb-4">
                        <h2 id="store-information-title" class="text-lg font-semibold text-stone-900">Store information</h2>
                        <p class="mt-1 text-sm text-stone-600">Your café’s name and location.</p>
                    </div>

                    <form id="store-edit-form" method="POST" action="{{ route('merchant.stores.update', $store) }}" class="mt-5 space-y-5">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="name" class="mb-1.5 block text-sm font-medium text-stone-800">Store name <span class="text-red-600" aria-hidden="true">*</span></label>
                            <x-ui.input id="name" name="name" type="text" value="{{ old('name', $store->name) }}" autocomplete="organization" required aria-describedby="name-help name-error" />
                            <p id="name-help" class="mt-1.5 text-xs text-stone-500">The store name shown to your customers.</p>
                            <x-input-error id="name-error" :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <label for="address" class="mb-1.5 block text-sm font-medium text-stone-800">Address <span class="font-normal text-stone-500">(optional)</span></label>
                            <x-ui.input id="address" name="address" type="text" value="{{ old('address', $store->address) }}" autocomplete="street-address" aria-describedby="address-help address-error" />
                            <p id="address-help" class="mt-1.5 text-xs text-stone-500">Useful when you manage more than one location.</p>
                            <x-input-error id="address-error" :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <div class="flex justify-end">
                            <x-ui.button type="submit" variant="primary" loading-text="Saving…">Save changes</x-ui.button>
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
                        <div class="mt-5 rounded-2xl bg-stone-50 p-4 sm:p-5">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="break-words text-base font-semibold text-stone-900">{{ $defaultProgram->name }}</h3>
                                <x-ui.badge variant="success">Default card</x-ui.badge>
                                @if($defaultProgram->trashed())
                                    <x-ui.badge variant="warning">Archived</x-ui.badge>
                                @endif
                            </div>
                            <dl class="mt-4 grid gap-4 border-t border-stone-200 pt-4 text-sm sm:grid-cols-3">
                                <div class="min-w-0"><dt class="text-stone-500">Reward</dt><dd class="mt-1 break-words font-semibold text-stone-900">{{ $defaultProgram->reward_title }}</dd></div>
                                <div><dt class="text-stone-500">Stamps needed</dt><dd class="mt-1 font-semibold text-stone-900">{{ $defaultProgram->reward_target }} stamps</dd></div>
                                <div><dt class="text-stone-500">Customers</dt><dd class="mt-1 font-semibold text-stone-900">{{ number_format($defaultProgram->loyalty_accounts_count ?? 0) }}</dd></div>
                            </dl>
                        </div>
                    @else
                        <x-ui.empty-state class="mt-5" heading="No loyalty card found" description="Create a loyalty card before sharing a join QR." />
                    @endif
                </section>


            </div>

            <div class="min-w-0 space-y-6">
            <section aria-labelledby="wallet-help-title" class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
                <h2 id="wallet-help-title" class="text-lg font-semibold text-stone-900">Wallet help</h2>
                <div class="mt-5 space-y-4">
                    <h2 class="font-semibold text-stone-900">Saved card updates</h2>
                    <x-ui.status-icon-badge :icon="$walletHealth['status_icon']" :label="$walletHealth['status_label']" :tone="$walletHealth['status_tone']" />
                    <p class="text-sm leading-6 text-stone-600">{{ $walletHealth['recommended_action'] }}</p>
                    <dl class="grid grid-cols-2 gap-3 border-t border-stone-100 pt-4 text-sm">
                        <div><dt class="text-stone-500">Customer cards</dt><dd class="mt-1 font-semibold text-stone-900">{{ number_format($walletHealth['active_cards']) }}</dd></div>
                        <div><dt class="text-stone-500">Apple Wallet registrations</dt><dd class="mt-1 font-semibold text-stone-900">{{ number_format($walletHealth['active_apple_registrations']) }}</dd></div>
                    </dl>
                    <p class="text-xs text-stone-500">Customers can use their browser card without adding it to Apple Wallet.</p>
                    @if(!$store->trashed())
                        <form method="POST" action="{{ route('merchant.stores.refresh-wallets', $store) }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" loading-text="Queueing…">Refresh store Wallets</x-ui.button>
                        </form>
                        <p class="text-xs text-stone-500">Queues an update for all customer cards at this store.</p>
                    @endif
                </div>
            </section>
                <section aria-labelledby="store-management-title" class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
                    <h2 id="store-management-title" class="text-lg font-semibold text-stone-900">Store management</h2>
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
                    <h2 id="archive-store-title" class="font-semibold text-red-900">{{ $store->trashed() ? 'Restore store' : 'Archive store' }}</h2>
                    <p class="mt-2 text-sm leading-6 text-red-800">{{ $store->trashed() ? 'Restoring re-enables join links, QR sharing, stamping, and redemption.' : 'Archiving pauses new joins and store activity. Existing customers, passes, and history remain preserved.' }}</p>
                    <form method="POST" action="{{ $store->trashed() ? route('merchant.stores.restore', $store) : route('merchant.stores.destroy', $store) }}" class="mt-4" @unless($store->trashed()) onsubmit="return confirm('Archive this store? New joins, stamping, and redemption will pause. Customer history will remain preserved.');" @endunless>
                        @csrf
                        @unless($store->trashed()) @method('DELETE') @endunless
                        <x-ui.button type="submit" :variant="$store->trashed() ? 'secondary' : 'danger'" :loading-text="$store->trashed() ? 'Restoring…' : 'Archiving…'">{{ $store->trashed() ? 'Restore store' : 'Archive store' }}</x-ui.button>
                    </form>
                </div></section>
            </div>
        </div>
    </div>
</x-merchant-design-layout>
