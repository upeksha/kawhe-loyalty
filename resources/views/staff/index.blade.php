<x-merchant-design-layout title="Your team" subtitle="Manage your team and their access at each café.">
<div class="team-page space-y-6">
    @if(session('status'))<p role="status" class="rounded-xl bg-brand-50 p-4 text-sm text-brand-800">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @php
        $staffLogins = $members->flatten(1)->unique('user_id')->count();
        $pendingCount = $invitations->flatten(1)->filter(fn ($invitation) => $invitation->expires_at->isFuture())->count();
    @endphp
    @if($stores->isNotEmpty())
        <div class="grid grid-cols-3 gap-3">
            @foreach([['Stores', $stores->count()], ['Staff logins', $staffLogins], ['Pending invites', $pendingCount]] as [$label, $count])
                <div class="rounded-xl border border-stone-200 bg-white p-4 sm:p-5"><p class="text-xs font-medium text-stone-500 sm:text-sm">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-brand-900">{{ $count }}</p></div>
            @endforeach
        </div>
        <div class="flex items-start gap-3 rounded-xl bg-brand-50 p-4 text-sm text-brand-900">
            <x-merchant-icon name="team" class="mt-0.5 size-5 shrink-0" />
            <div><p class="font-semibold">Your owner account is ready to use</p><p class="mt-1 leading-relaxed">Your owner login works in the free Kawhe Merchant app for stamping and redeeming. Invite staff only when someone else needs their own login. The owner does not use a staff place.</p></div>
        </div>
        @if($stores->count() > 1)
            <nav aria-label="Jump to a store team" class="flex flex-wrap gap-2">
                @foreach($stores as $store)
                    <a href="#team-store-{{ $store->id }}" class="team-action inline-flex items-center gap-2 rounded-lg border border-stone-200 bg-white px-3 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50"><x-merchant-icon name="stores" class="size-4" />{{ $store->name }}<span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600">{{ $members->get($store->id, collect())->count() }}</span></a>
                @endforeach
            </nav>
        @endif
    @endif
    @forelse($stores as $store)
        @php
            $capacity = $staffCapacity[$store->id];
            $storeMembers = $members->get($store->id, collect());
        @endphp
        <section id="team-store-{{ $store->id }}" aria-labelledby="team-store-title-{{ $store->id }}" class="scroll-mt-6 overflow-hidden rounded-2xl border border-stone-200 bg-white">
            <header class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-100 bg-stone-50/60 px-5 py-5 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl border border-stone-200 bg-white text-brand-800">@if($store->logo_url)<img src="{{ $store->logo_url }}" alt="" class="h-full w-full object-contain p-1.5">@else<x-merchant-icon name="stores" class="size-5" />@endif</span>
                    <div class="min-w-0"><h2 id="team-store-title-{{ $store->id }}" class="break-words text-lg font-semibold text-stone-900">{{ $store->name }}</h2><p class="mt-1 text-xs text-stone-500">{{ $store->address ?: 'Team access for this café' }}</p></div>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-medium"><span class="rounded-full bg-brand-50 px-2.5 py-1 text-brand-800">{{ $storeMembers->count() }} staff</span><span class="rounded-full bg-stone-100 px-2.5 py-1 text-stone-600">{{ $capacity['limit'] === null ? 'Pro · unlimited staff' : 'Free · '.$capacity['occupied'].' / '.$capacity['limit'].' places' }}</span></div>
            </header>
            <div class="px-5 sm:px-6">
                <div class="team-member-row hidden border-b border-stone-100 py-3 text-xs font-semibold uppercase tracking-wide text-stone-500 md:grid" aria-hidden="true"><span>Member</span><span>Permissions</span><span>Status</span><span class="sr-only">Actions</span></div>
                <div class="team-member-row py-4">
                    <div class="team-person flex min-w-0 items-center gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-semibold text-brand-800">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="text-sm font-semibold text-stone-900">{{ auth()->user()->name }}</p><span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600">Owner · you</span></div><p class="mt-1 break-all text-xs text-stone-500">{{ auth()->user()->email }}</p></div></div>
                    <p class="text-xs leading-relaxed text-stone-600">Full access, including stamps and rewards</p>
                    <span class="team-status w-fit rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Active</span><span></span>
                </div>
                @forelse($storeMembers as $member)
                    <div x-data="{ editing: false }" class="border-t border-stone-100 py-4">
                        <div class="team-member-row">
                            <div class="team-person flex min-w-0 items-center gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-full bg-stone-100 text-sm font-semibold text-stone-600">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($member->user->name, 0, 1)) }}</span><div class="min-w-0"><p class="break-words text-sm font-semibold text-stone-900">{{ $member->user->name }}</p><p class="mt-1 break-all text-xs text-stone-500">{{ $member->user->email }}</p></div></div>
                            <div class="flex flex-wrap gap-1.5 text-xs">@if($member->can_stamp)<span class="rounded-md bg-brand-50 px-2 py-1 text-brand-800">Stamp cards</span>@endif @if($member->can_redeem)<span class="rounded-md bg-stone-100 px-2 py-1 text-stone-700">Redeem rewards</span>@endif @if(!$member->can_stamp && !$member->can_redeem)<span class="text-stone-500">No actions allowed</span>@endif</div>
                            <span class="team-status w-fit rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Active</span>
                            <button type="button" @click="editing = !editing" :aria-expanded="editing" aria-controls="member-permissions-{{ $member->id }}" aria-label="Edit permissions for {{ $member->user->name }} at {{ $store->name }}" title="Edit permissions" class="team-action team-edit team-icon-action inline-flex items-center justify-center rounded-lg text-brand-800 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700"><x-merchant-icon name="edit" /></button>
                        </div>
                        <div id="member-permissions-{{ $member->id }}" x-show="editing" x-cloak class="mt-4 space-y-4 rounded-xl border border-stone-200 bg-stone-50 p-4">
                            <p class="text-sm font-semibold">Permissions for {{ $member->user->name }}</p>
                            <form method="POST" action="{{ route('merchant.staff.update', [$store, $member]) }}" class="space-y-4">
                                @csrf @method('PATCH')
                                <fieldset><legend class="mb-3 text-sm font-semibold">Permissions</legend><div class="flex flex-wrap gap-4 text-sm"><label class="flex items-center gap-2"><input type="checkbox" name="can_stamp" value="1" @checked($member->can_stamp)> Stamp cards</label><label class="flex items-center gap-2"><input type="checkbox" name="can_redeem" value="1" @checked($member->can_redeem)> Redeem rewards</label></div></fieldset>
                                <div class="flex flex-wrap gap-2"><x-ui.button type="submit" variant="primary" size="sm" class="team-action">Save permissions</x-ui.button><button type="button" @click="editing = false" class="team-action inline-flex items-center rounded-lg px-3 py-2 text-sm text-stone-600 hover:bg-stone-100">Cancel</button></div>
                            </form>
                            <form method="POST" action="{{ route('merchant.staff.revoke', [$store, $member]) }}" onsubmit="return confirm('Remove this staff member’s access to this store? They will no longer be able to stamp or redeem here.');">@csrf @method('DELETE')<x-ui.button type="submit" variant="secondary" size="sm" class="team-action text-red-700">Remove access</x-ui.button></form>
                        </div>
                    </div>
                @empty<p class="border-t border-dashed border-stone-200 py-4 text-sm text-stone-500">No staff have joined this store yet.</p>@endforelse
                <div class="space-y-3 border-t border-stone-100 py-4"><p class="text-xs text-stone-500">@if($capacity['limit'] === null)Pro includes unlimited staff for this café.@else{{ $capacity['occupied'] }} of {{ $capacity['limit'] }} staff places used on Free, including pending invitations. The owner does not use a staff place.@endif</p>
            @if($capacity['limit'] !== null && $capacity['occupied'] >= $capacity['limit'])
                <div class="max-w-[620px] rounded-xl border border-stone-200 bg-stone-50 p-4 text-sm text-stone-700">Both Free staff places are in use. Cancel a pending invitation, remove a staff member, or <a href="{{ route('billing.index') }}" class="font-semibold text-brand-700 underline underline-offset-2">upgrade to Pro</a> to invite more people.</div>
            @else
            <details class="team-invite" @if($errors->any() && old('invitation_store_id') == $store->id) open @endif>
                <summary class="team-invite-toggle inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-brand-700 px-3 py-2 text-sm font-semibold text-white"><x-merchant-icon name="team" class="size-4" />Invite staff</summary>
                <form method="POST" action="{{ route('merchant.staff.invite', $store) }}" class="mt-4 max-w-xl space-y-4 rounded-xl border border-stone-200 bg-stone-50 p-4 sm:p-5">
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
                    <x-ui.button type="submit" class="team-action" data-loading-text="Sending invitation…">Send invitation</x-ui.button>
                </form>
            </details>
            @endif
                </div>
            </div>
            <section aria-labelledby="pending-invitations-{{ $store->id }}" class="border-t border-stone-100 px-5 py-5 sm:px-6">
                <div class="flex items-center gap-2"><h3 id="pending-invitations-{{ $store->id }}" class="text-base font-semibold text-stone-900">Invitations</h3><span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">{{ $invitations->get($store->id, collect())->count() }}</span></div>
                <div class="mt-4 space-y-3">
                    @forelse($invitations->get($store->id, collect()) as $invitation)
                        <article class="flex flex-col gap-3 rounded-xl border border-stone-200 bg-stone-50/50 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
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
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('merchant.staff.invite', $store) }}">@csrf
                                    <input type="hidden" name="email" value="{{ $invitation->email }}"><input type="hidden" name="can_stamp" value="{{ (int)$invitation->can_stamp }}"><input type="hidden" name="can_redeem" value="{{ (int)$invitation->can_redeem }}">
                                    <x-ui.button type="submit" variant="secondary" size="sm" class="team-action whitespace-nowrap" aria-label="Resend invitation to {{ $invitation->email }}">Resend</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('merchant.staff.cancel', [$store, $invitation]) }}">@csrf @method('DELETE')<button type="submit" aria-label="Cancel invitation to {{ $invitation->email }}" class="team-action inline-flex items-center justify-center rounded-xl border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-700 transition-colors hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Cancel</button></form>
                            </div>
                        </article>
                    @empty<p class="text-sm text-stone-500">No pending invitations. New invitations will appear here until accepted.</p>@endforelse
                </div>
            </section>
        </section>
    @empty
        <x-ui.card class="dashboard-panel p-6"><p>No active stores are available. Create or restore a store to invite staff.</p><x-ui.button href="{{ route('merchant.stores.index') }}" variant="secondary" class="mt-4">View stores</x-ui.button></x-ui.card>
    @endforelse
    @if($stores->isNotEmpty())<p class="text-xs text-stone-500">Staff cannot manage your stores, billing, customer lists, or other staff.</p>@endif
</div>
</x-merchant-design-layout>
