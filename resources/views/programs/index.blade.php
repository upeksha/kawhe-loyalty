<x-merchant-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-8">
            <div>
                <p class="text-sm text-stone-500">{{ $store->name }}</p>
                <h1 class="text-2xl font-bold text-stone-900">Loyalty Cards</h1>
                <p class="mt-1 text-sm text-stone-600">Your default card lives here alongside any additional cards. Each card has its own reward settings, QR code, join link, and customer list.</p>
            </div>
            <div class="flex gap-3">
                <x-ui.button href="{{ route('merchant.stores.edit', $store) }}" variant="secondary" size="sm">Store & Default Card</x-ui.button>
                <x-ui.button href="{{ route('merchant.stores.programs.create', $store) }}" variant="primary" size="sm">Add loyalty card</x-ui.button>
            </div>
        </div>

        <div class="grid gap-4">
            @foreach($programs as $program)
                <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-semibold text-stone-900">{{ $program->name }}</h2>
                                @if($program->is_default)
                                    <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">Default</span>
                                @endif
                                @if($program->trashed())
                                    <span class="inline-flex rounded-full bg-stone-100 px-2.5 py-1 text-xs font-semibold text-stone-600">Archived</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-stone-600">{{ $program->reward_target }} stamps for {{ $program->reward_title }}</p>
                            <p class="mt-1 text-xs text-stone-500">{{ $program->loyalty_accounts_count }} customer cards joined</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if(! $program->trashed())
                                <x-ui.button href="{{ route('merchant.stores.programs.qr', [$store, $program]) }}" variant="ghost" size="sm">QR Code</x-ui.button>
                                <x-ui.button href="{{ route('merchant.stores.programs.edit', [$store, $program]) }}" variant="secondary" size="sm">Edit</x-ui.button>
                                @if(! $program->is_default)
                                    <form method="POST" action="{{ route('merchant.stores.programs.destroy', [$store, $program]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" variant="ghost" size="sm">Archive</x-ui.button>
                                    </form>
                                @endif
                            @else
                                <form method="POST" action="{{ route('merchant.stores.programs.restore', [$store, $program->id]) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm">Restore</x-ui.button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-merchant-layout>
