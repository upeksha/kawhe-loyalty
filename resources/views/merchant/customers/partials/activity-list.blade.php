<ul class="mt-4 divide-y divide-stone-100">
    @foreach($activityEvents as $event)
        <li class="flex flex-col justify-between gap-2 py-3 sm:flex-row">
            <div>
                <p class="text-sm font-semibold text-stone-900">{{ $event->type === 'redeem' ? 'Reward redeemed' : ($event->type === 'stamp' ? 'Stamps added' : ucfirst($event->type)) }}@if($event->count !== null) <span class="ml-1 font-normal text-stone-500">· {{ $event->count }}</span>@endif</p>
                <p class="mt-1 text-xs text-stone-500">By {{ $event->user->name ?? 'System' }}</p>
            </div>
            <p class="shrink-0 text-xs text-stone-500">{{ $event->created_at->format('M d, Y g:i A') }}</p>
        </li>
    @endforeach
</ul>
