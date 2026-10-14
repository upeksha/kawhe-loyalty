<x-merchant-design-layout title="Your team" subtitle="Give each staff member access to the cafés and actions they need.">
    @if(session('status'))<p role="status" class="rounded-lg bg-brand-50 p-4 text-brand-700">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @forelse($stores as $store)
        <section aria-labelledby="team-store-{{ $store->id }}" class="space-y-6">
            <h2 id="team-store-{{ $store->id }}" class="text-xl font-semibold">{{ $store->name }}</h2>
            <x-ui.card class="dashboard-panel space-y-4 p-6">
                <div class="hidden grid-cols-4 gap-4 text-sm font-semibold text-stone-600 md:grid" aria-hidden="true"><span>Member</span><span>Store</span><span>Permissions</span><span>Status</span></div>
                <div class="grid gap-3 md:grid-cols-4">
                    <p>{{ auth()->user()->name }} · owner</p><p>{{ $store->name }}</p><p>Full access</p><p>Active</p>
                </div>
                @forelse($members->get($store->id, collect()) as $member)
                    <div class="grid gap-3 border-t border-stone-100 pt-4 md:grid-cols-4">
                        <div><p>{{ $member->user->name }} · staff</p><p class="mt-1 break-words text-sm text-stone-600">{{ $member->user->email }}</p></div>
                        <p>{{ $store->name }}</p>
                        <p>{{ $member->can_stamp ? 'Stamp cards' : '' }}{{ $member->can_stamp && $member->can_redeem ? ' & ' : '' }}{{ $member->can_redeem ? 'Redeem rewards' : '' }}{{ !$member->can_stamp && !$member->can_redeem ? 'No actions allowed' : '' }}</p>
                        <p>Active</p>
                    </div>
                @empty<p class="text-sm text-stone-500">No staff have joined this store yet.</p>@endforelse
                <p class="text-sm text-stone-600">Staff cannot manage your stores, billing, customer lists, or other staff.</p>
            </x-ui.card>
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
                            <input type="hidden" name="can_redeem" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="can_redeem" value="1" @checked(old('invitation_store_id') == $store->id ? old('can_redeem') : true)> Redeem rewards</label>
                        </div>
                    </fieldset>
                    <p class="text-sm text-stone-600">Invitations expire in seven days. Your team member gets their own login; they do not need to create a store.</p>
                    <x-ui.button type="submit" class="w-full" data-loading-text="Sending invitation…">Send invitation</x-ui.button>
                </form>
            </details>
            @foreach($members->get($store->id, collect()) as $member)
                <details class="max-w-[660px]">
                    <summary class="flex max-w-[400px] min-h-12 cursor-pointer items-center justify-center rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm font-semibold text-brand-700">Manage {{ $member->user->name }}’s permissions</summary>
                    <div class="mt-6 space-y-4 rounded-2xl border border-stone-200 bg-white p-6">
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
                </details>
            @endforeach
            <details class="max-w-[660px]">
                <summary class="flex max-w-[400px] min-h-12 cursor-pointer items-center justify-center rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm font-semibold text-brand-700">Pending invitations ({{ $invitations->get($store->id, collect())->count() }})</summary>
                <div class="mt-6 space-y-6">
                    @forelse($invitations->get($store->id, collect()) as $invitation)
                        <article class="space-y-4 rounded-2xl border border-stone-200 bg-white p-6">
                            <h3 class="break-words text-lg font-semibold">{{ $invitation->email }}</h3>
                            <dl class="space-y-4">
                                <div><dt class="text-sm font-semibold">Permissions</dt><dd class="mt-2 text-stone-600">{{ $invitation->can_stamp ? 'Stamp cards' : '' }}{{ $invitation->can_stamp && $invitation->can_redeem ? ' and ' : '' }}{{ $invitation->can_redeem ? 'Redeem rewards' : '' }}</dd></div>
                                <div><dt class="text-sm font-semibold">Invitation status</dt><dd class="mt-2 text-stone-600">{{ $invitation->expires_at->isPast() ? 'Expired' : 'Waiting for acceptance · Expires '.$invitation->expires_at->diffForHumans() }}</dd></div>
                            </dl>
                            <div class="grid max-w-[400px] gap-4">
                                <form method="POST" action="{{ route('merchant.staff.invite', $store) }}">@csrf
                                    <input type="hidden" name="email" value="{{ $invitation->email }}"><input type="hidden" name="can_stamp" value="{{ (int)$invitation->can_stamp }}"><input type="hidden" name="can_redeem" value="{{ (int)$invitation->can_redeem }}">
                                    <x-ui.button type="submit" class="w-full">Resend invitation</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('merchant.staff.cancel', [$store, $invitation]) }}">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger" class="w-full">Cancel invitation</x-ui.button></form>
                            </div>
                        </article>
                    @empty<p class="text-sm text-stone-500">No pending invitations.</p>@endforelse
                </div>
            </details>
        </section>
    @empty
        <x-ui.card class="dashboard-panel p-6"><p>No active stores are available. Create or restore a store to invite staff.</p><x-ui.button href="{{ route('merchant.stores.index') }}" variant="secondary" class="mt-4">View stores</x-ui.button></x-ui.card>
    @endforelse
</x-merchant-design-layout>
