<x-merchant-layout>
    <x-slot name="header">Staff access</x-slot>
    <div class="max-w-4xl mx-auto space-y-6">
        <p class="text-stone-600">Invite your team to stamp cards or redeem rewards at a specific store. Staff cannot manage your stores, billing, customer lists, or other staff.</p>
        @if(session('status'))<p role="status" class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('status') }}</p>@endif
        @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @forelse($stores as $store)
        <x-ui.card class="p-6 space-y-6">
            <h2 class="text-xl font-semibold">{{ $store->name }}</h2>
            <form method="POST" action="{{ route('merchant.staff.invite', $store) }}" class="space-y-3">
                @csrf
                <label class="block text-sm font-medium">Staff email
                    <input type="email" name="email" required maxlength="255" value="{{ old('email') }}" class="mt-1 block w-full rounded-lg border-stone-300" placeholder="staff@example.com">
                </label>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label><input type="checkbox" name="can_stamp" value="1" checked> Stamp cards</label>
                    <label><input type="checkbox" name="can_redeem" value="1" checked> Redeem rewards</label>
                </div>
                <x-ui.button type="submit">Send invitation</x-ui.button>
                <p class="text-xs text-stone-500">Invitations expire in seven days. Your team member gets their own login; they do not need to create a store.</p>
            </form>
            <div class="space-y-3">
                <h3 class="font-semibold">Team members</h3>
                @forelse($members->get($store->id, collect()) as $member)
                <div class="rounded-lg border border-stone-200 p-4 space-y-3">
                    <p class="font-medium">{{ $member->user->name }}</p><p class="text-sm text-stone-600 break-all">{{ $member->user->email }}</p>
                    <form method="POST" action="{{ route('merchant.staff.update', [$store, $member]) }}" class="flex flex-wrap items-center gap-4 text-sm">
                        @csrf @method('PATCH')
                        <label><input type="checkbox" name="can_stamp" value="1" @checked($member->can_stamp)> Stamp cards</label>
                        <label><input type="checkbox" name="can_redeem" value="1" @checked($member->can_redeem)> Redeem rewards</label>
                        <x-ui.button type="submit" variant="secondary">Save permissions</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('merchant.staff.revoke', [$store, $member]) }}">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger">Remove access</x-ui.button></form>
                </div>
                @empty<p class="text-sm text-stone-500">No staff have joined this store yet.</p>@endforelse
            </div>
            <div class="space-y-3">
                <h3 class="font-semibold">Pending invitations</h3>
                @forelse($invitations->get($store->id, collect()) as $invitation)
                <div class="rounded-lg border border-stone-200 p-4 space-y-2">
                    <p class="text-sm break-all">{{ $invitation->email }}</p>
                    <p class="text-xs text-stone-500">{{ $invitation->can_stamp ? 'Stamp cards' : '' }} {{ $invitation->can_stamp && $invitation->can_redeem ? 'and' : '' }} {{ $invitation->can_redeem ? 'Redeem rewards' : '' }} · {{ $invitation->expires_at->isPast() ? 'Expired' : 'Expires '.$invitation->expires_at->diffForHumans() }}</p>
                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('merchant.staff.invite', $store) }}">@csrf
                            <input type="hidden" name="email" value="{{ $invitation->email }}"><input type="hidden" name="can_stamp" value="{{ (int)$invitation->can_stamp }}"><input type="hidden" name="can_redeem" value="{{ (int)$invitation->can_redeem }}">
                            <x-ui.button type="submit" variant="secondary">Resend invitation</x-ui.button>
                        </form>
                        <form method="POST" action="{{ route('merchant.staff.cancel', [$store, $invitation]) }}">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger">Cancel invitation</x-ui.button></form>
                    </div>
                </div>
                @empty<p class="text-sm text-stone-500">No pending invitations.</p>@endforelse
            </div>
        </x-ui.card>
        @empty<p>No active stores are available. Create or restore a store to invite staff.</p>@endforelse
    </div>
</x-merchant-layout>
