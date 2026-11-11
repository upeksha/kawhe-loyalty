<x-merchant-design-layout title="Your team" subtitle="Give each staff member access to the cafés and actions they need.">
    @if(session('status'))<p role="status" class="rounded-lg bg-brand-50 p-4 text-brand-700">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @forelse($stores as $store)
        @php $capacity = $staffCapacity[$store->id]; @endphp
        <section aria-labelledby="team-store-{{ $store->id }}" class="space-y-6">
            <h2 id="team-store-{{ $store->id }}" class="text-xl font-semibold">{{ $store->name }}</h2>
            <p class="text-sm text-stone-600">Your owner login works in the free Kawhe Merchant app for stamping and redeeming. Invite staff only when someone else needs their own login.</p>
            <p class="text-sm text-stone-600">@if($capacity['limit'] === null)Pro includes unlimited staff for this café.@else{{ $capacity['occupied'] }} of {{ $capacity['limit'] }} staff places used on Free, including pending invitations. The owner does not use a staff place.@endif</p>
            <x-ui.card class="dashboard-panel space-y-4 p-6">
                <div class="hidden grid-cols-4 gap-4 text-sm font-semibold text-stone-600 md:grid" aria-hidden="true"><span>Member</span><span>Store</span><span>Permissions</span><span>Status</span></div>
                <div class="grid gap-3 md:grid-cols-4">
                    <p>{{ auth()->user()->name }} · owner</p><p>{{ $store->name }}</p><p>Full access, including stamps and rewards</p><p>Active</p>
                </div>
                @forelse($members->get($store->id, collect()) as $member)
                    <div x-data="{ editing: false }" class="border-t border-stone-100 pt-4">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div class="min-w-0"><div class="flex items-center gap-2"><p class="break-words">{{ $member->user->name }} · staff</p><button type="button" @click="editing = !editing" :aria-expanded="editing" aria-controls="member-permissions-{{ $member->id }}" aria-label="Edit permissions for {{ $member->user->name }} at {{ $store->name }}" title="Edit permissions" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-brand-700 hover:bg-brand-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15l-1 6Z" /></svg></button></div><p class="mt-1 break-words text-sm text-stone-600">{{ $member->user->email }}</p></div>
                        <p>{{ $store->name }}</p>
                        <p>{{ $member->can_stamp ? 'Stamp cards' : '' }}{{ $member->can_stamp && $member->can_redeem ? ' & ' : '' }}{{ $member->can_redeem ? 'Redeem rewards' : '' }}{{ !$member->can_stamp && !$member->can_redeem ? 'No actions allowed' : '' }}</p>
                        <p>Active</p>
                    </div>
                    <div id="member-permissions-{{ $member->id }}" x-show="editing" x-cloak class="mt-4 space-y-4 rounded-xl border border-stone-200 bg-stone-50 p-4">
                        <p class="font-semibold">{{ $member->user->name }} · {{ $store->name }}</p><p class="break-words text-sm text-stone-600">{{ $member->user->email }}</p>
                        <form method="POST" action="{{ route('merchant.staff.update', [$store, $member]) }}" class="space-y-4">
                            @csrf @method('PATCH')
                            <fieldset><legend class="mb-3 text-sm font-semibold">Permissions</legend><div class="flex flex-wrap gap-4 text-sm">
                                <label class="flex items-center gap-2"><input type="checkbox" name="can_stamp" value="1" @checked($member->can_stamp)> Stamp cards</label>
                                <label class="flex items-center gap-2"><input type="checkbox" name="can_redeem" value="1" @checked($member->can_redeem)> Redeem rewards</label>
                            </div></fieldset>
                            <x-ui.button type="submit" variant="secondary" class="w-full">Save permissions</x-ui.button>
                        </form>
                        <form method="POST" action="{{ route('merchant.staff.revoke', [$store, $member]) }}" onsubmit="return confirm('Remove this staff member’s access to this store? They will no longer be able to stamp or redeem here.');">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger" class="w-full">Remove access</x-ui.button></form>
                    </div>
                    </div>
                @empty<p class="text-sm text-stone-500">No staff have joined this store yet.</p>@endforelse
                <p class="text-sm text-stone-600">Staff cannot manage your stores, billing, customer lists, or other staff.</p>
            </x-ui.card>
            @if($capacity['limit'] !== null && $capacity['occupied'] >= $capacity['limit'])
                <div class="max-w-[620px] rounded-xl border border-stone-200 bg-stone-50 p-4 text-sm text-stone-700">Both Free staff places are in use. Cancel a pending invitation, remove a staff member, or <a href="{{ route('billing.index') }}" class="font-semibold text-brand-700 underline underline-offset-2">upgrade to Pro</a> to invite more people.</div>
            @else
            <details class="team-invite max-w-[620px]" @if($errors->any() && old('invitation_store_id') == $store->id) open @endif>
                <summary class="flex max-w-[260px] min-h-12 cursor-pointer items-center justify-center rounded-lg bg-brand-600 px-4 py-3 text-sm font-semibold text-white">Invite staff</summary>
                <form method="POST" action="{{ route('merchant.staff.invite', $store) }}" class="mt-6 space-y-6">
                    @csrf
                    <input type="hidden" name="invitation_store_id" value="{{ $store->id }}">
                    <h3 class="text-xl font-semibold">Invite a staff member</h3>
                    <div><label for="staff-email-{{ $store->id }}" class="block text-sm font-semibold">Email address</label>
                        <x-ui.input id="staff-email-{{ $store->id }}" type="email" name="email" required maxlength="255" value="{{ old('invitation_store_id') == $store->id ? old('email') : '' }}" class="mt-2" placeholder="staff@example.com" />
                    </div>
                    <div class="space-y-2 rounded-lg border border-stone-200 bg-white px-4 py-3"><p class="text-sm font-semibold">Store</p><p>{{ $store->name }}</p></div>
                    <fieldset class="rounded-lg border border-stone-200 bg-white p-4">
                        <legend class="px-1 text-sm font-semibold">Permissions</legend>
                        <div class="flex flex-wrap gap-4 text-sm">
                            <input type="hidden" name="can_stamp" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="can_stamp" value="1" @checked(old('invitation_store_id') == $store->id ? old('can_stamp') : true)> Stamp cards</label>
                            <input type="hidden" name="can_redeem" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="can_redeem" value="1" @checked(old('invitation_store_id') == $store->id ? old('can_redeem') : false)> Redeem rewards</label>
                        </div>
                    </fieldset>
                    <p class="text-sm text-stone-600">Invitations expire in seven days. Your team member gets their own login; they do not need to create a store.</p>
                    <x-ui.button type="submit" class="w-full" data-loading-text="Sending invitation…">Send invitation</x-ui.button>
                </form>
            </details>
            @endif
            <section aria-labelledby="pending-invitations-{{ $store->id }}" class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
                <div class="flex items-center gap-2"><h3 id="pending-invitations-{{ $store->id }}" class="text-base font-semibold text-stone-900">Pending invitations</h3><span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">{{ $invitations->get($store->id, collect())->count() }}</span></div>
                <div class="mt-4 space-y-3">
                    @forelse($invitations->get($store->id, collect()) as $invitation)
                        <article class="flex flex-col gap-4 rounded-xl border border-stone-200 bg-stone-50/50 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="break-all text-base font-semibold text-stone-900">{{ $invitation->email }}</h4>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $invitation->expires_at->isPast() ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800' }}">{{ $invitation->expires_at->isPast() ? 'Expired' : 'Pending' }}</span>
                                </div>
                                <dl class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-stone-600">
                                    <div class="flex flex-wrap gap-1"><dt class="font-medium text-stone-700">Access:</dt><dd>{{ $invitation->can_stamp ? 'Stamp cards' : '' }}{{ $invitation->can_stamp && $invitation->can_redeem ? ' and ' : '' }}{{ $invitation->can_redeem ? 'Redeem rewards' : '' }}</dd></div>
                                    @unless($invitation->expires_at->isPast())<div class="flex flex-wrap gap-1"><dt class="font-medium text-stone-700">Expires:</dt><dd>{{ $invitation->expires_at->diffForHumans() }}</dd></div>@endunless
                                </dl>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 lg:shrink-0 lg:justify-end">
                                <form method="POST" action="{{ route('merchant.staff.invite', $store) }}">@csrf
                                    <input type="hidden" name="email" value="{{ $invitation->email }}"><input type="hidden" name="can_stamp" value="{{ (int)$invitation->can_stamp }}"><input type="hidden" name="can_redeem" value="{{ (int)$invitation->can_redeem }}">
                                    <x-ui.button type="submit" variant="secondary" size="sm" class="min-h-10 whitespace-nowrap" aria-label="Resend invitation to {{ $invitation->email }}">Resend</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('merchant.staff.cancel', [$store, $invitation]) }}">@csrf @method('DELETE')<button type="submit" aria-label="Cancel invitation to {{ $invitation->email }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-700 transition-colors hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Cancel</button></form>
                            </div>
                        </article>
                    @empty<p class="text-sm text-stone-500">No pending invitations. New invitations will appear here until accepted.</p>@endforelse
                </div>
            </section>
        </section>
    @empty
        <x-ui.card class="dashboard-panel p-6"><p>No active stores are available. Create or restore a store to invite staff.</p><x-ui.button href="{{ route('merchant.stores.index') }}" variant="secondary" class="mt-4">View stores</x-ui.button></x-ui.card>
    @endforelse
</x-merchant-design-layout>
