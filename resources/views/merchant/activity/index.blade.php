<x-merchant-design-layout title="Activity" subtitle="A clear record of stamps earned and rewards redeemed.">
    <section aria-label="Activity log" class="rounded-2xl border border-stone-200 bg-white p-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-stone-600">See stamps and redemptions across your stores.</p>
            @if($events->isNotEmpty())
                <x-ui.button href="{{ route('merchant.activity.export') }}" variant="secondary">Download this month’s activity CSV</x-ui.button>
            @endif
        </div>
        @if($events->isEmpty())
            <div class="space-y-3 py-4">
                <h2 class="text-xl font-semibold">No activity yet</h2>
                <p class="text-base text-stone-600">Stamps and redeemed rewards across your stores will appear here.</p>
            </div>
        @else
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Stamp and reward history">
                <table class="w-full min-w-[640px] table-fixed text-left text-base">
                    <caption class="sr-only">Stamps and reward redemptions across your stores, newest first</caption>
                    <thead class="text-sm text-stone-600">
                        <tr>@foreach(['Customer', 'Action', 'Staff member', 'When'] as $label)<th scope="col" class="pb-4 pr-4 font-semibold">{{ $label }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach($events as $event)
                            <tr class="align-top">
                                <td class="py-3 pr-4">
                                    {{ $event->loyaltyAccount?->customer?->name ?: ($event->loyaltyAccount?->customer?->email ?? 'Customer unavailable') }}
                                    <p class="mt-1 text-sm text-stone-600">{{ $event->store?->name ?? 'Store unavailable' }}</p>
                                </td>
                                <td class="py-3 pr-4">{{ number_format($event->count) }} {{ match ($event->type) {
                                        'stamp' => Str::plural('stamp', $event->count).' added',
                                        'stamp_undo' => Str::plural('stamp', $event->count).' undone',
                                        default => Str::plural('reward', $event->count).' redeemed',
                                    } }}</td>
                                <td class="py-3 pr-4">{{ $event->user?->name ?? 'Staff member unavailable' }}</td>
                                <td class="py-3"><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M d, Y g:i A') }}</time></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($events->hasPages())<div class="mt-6">{{ $events->links() }}</div>@endif
        @endif
    </section>
</x-merchant-design-layout>
