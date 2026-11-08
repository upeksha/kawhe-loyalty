<x-admin-layout>
    <x-slot name="header">Café signups</x-slot>
    <div class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-700">Grow Kawhe</p>
            <h1 class="mt-2 text-3xl font-semibold text-stone-900">See who’s getting started.</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-stone-600">Follow café owners from signup to a ready counter. Dates are shown in New Zealand time. Staff-only accounts and platform administrators are excluded.</p>
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach(['today' => 'Signed up today', 'week' => 'Last 7 days', 'pending' => 'Awaiting email verification', 'ready' => 'At least one café set up'] as $key => $label)
                <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <p class="text-3xl font-semibold text-brand-700">{{ number_format($stats[$key]) }}</p>
                    <p class="mt-2 text-sm text-stone-600">{{ $label }}</p>
                </div>
            @endforeach
        </div>
        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <p class="text-sm text-stone-600">Signup and verification alerts: <strong class="text-stone-900">{{ config('admin.signup_alert_email') ?: 'Not configured' }}</strong> · {{ $stats['alerts_pending'] }} pending</p>
            @if($stats['alerts_failed'])
                <p role="alert" class="mt-2 font-medium text-red-700">{{ $stats['alerts_failed'] }} alert(s) could not be sent after three attempts. Check email delivery before retrying.</p>
            @endif
        </div>
        <form method="GET" action="{{ route('admin.merchants.index') }}" class="grid gap-4 rounded-2xl border border-stone-200 bg-white p-5 sm:grid-cols-2 xl:grid-cols-4">
            <div><label for="q" class="mb-1 block text-sm font-medium text-stone-700">Café, owner or email</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" class="w-full rounded-xl border-stone-300 text-sm" placeholder="Search signups"></div>
            <div><label for="verification" class="mb-1 block text-sm font-medium text-stone-700">Email status</label><select id="verification" name="verification" class="w-full rounded-xl border-stone-300 text-sm"><option value="">All email statuses</option value="verified" @selected(($filters['verification'] ?? '') === 'verified')>Verified</option><option value="pending" @selected(($filters['verification'] ?? '') === 'pending')>Awaiting verification</option></select></div>
            <div><label for="setup" class="mb-1 block text-sm font-medium text-stone-700">Café setup</label><select id="setup" name="setup" class="w-full rounded-xl border-stone-300 text-sm"><option value="">All setup stages</option><option value="ready" @selected(($filters['setup'] ?? '') === 'ready')>At least one café ready</option><option value="incomplete" @selected(($filters['setup'] ?? '') === 'incomplete')>No completed setup recorded</option></select></div>
            <div><label for="campaign" class="mb-1 block text-sm font-medium text-stone-700">Campaign tag</label><input id="campaign" name="campaign" value="{{ $filters['campaign'] ?? '' }}" maxlength="120" class="w-full rounded-xl border-stone-300 text-sm" placeholder="e.g. cafe_launch"></div>
            <div class="flex items-center gap-4 sm:col-span-2 xl:col-span-4"><x-ui.button type="submit" variant="primary">Apply filters</x-ui.button><a href="{{ route('admin.merchants.index') }}" class="text-sm font-medium text-brand-700 underline underline-offset-4">Clear filters</a></div>
        </form>
        <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg font-semibold text-stone-900">{{ $merchants->total() }} café owner{{ $merchants->total() === 1 ? '' : 's' }}</h2><span class="text-sm text-stone-500">Newest first</span></div>
        <div class="space-y-4">
            @forelse($merchants as $merchant)
                <article class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                    <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2"><h3 class="break-words text-lg font-semibold text-stone-900">{{ $merchant->name }}</h3><x-ui.badge :variant="$merchant->admin_plan_label === 'Pro' ? 'success' : 'neutral'">{{ $merchant->admin_plan_label }}</x-ui.badge></div>
                            <p class="mt-2 break-all text-sm text-stone-600">{{ $merchant->email }}</p>
                            <p class="mt-3 text-xs text-stone-500">Joined {{ $merchant->created_at->timezone('Pacific/Auckland')->format('j M Y, g:i a T') }}</p>
                            <p class="mt-2 text-sm {{ $merchant->hasVerifiedEmail() ? 'text-brand-700' : 'text-amber-800' }}">{{ $merchant->hasVerifiedEmail() ? 'Email verified' : 'Awaiting email verification' }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">Café setup</p>
                            @foreach($merchant->stores as $store)
                                <div class="mt-2"><p class="break-words text-sm font-semibold text-stone-900">{{ $store->name }}</p><p class="mt-1 text-sm text-stone-600">@if($store->onboarding_completed_at)Ready · setup completed @elseif($store->onboarding_step){{ ['store_basics' => 'Store basics', 'card_design' => 'Choosing card design', 'customer_form' => 'Setting customer details', 'card_ready' => 'Card ready · finish setup', 'continue_trial' => 'Card ready · finish setup'][$store->onboarding_step] ?? 'Setup in progress' }}@else Setup status not recorded @endif</p></div>
                            @endforeach
                        </div>
                        <div class="min-w-0">
                            @php($tags = $merchant->signup_attribution ?? [])
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">How they found Kawhe</p>
                            <p class="break-words text-sm font-semibold text-stone-900">{{ $tags['utm_source'] ?? 'Unattributed' }}@if(isset($tags['utm_medium'])) · {{ $tags['utm_medium'] }}@endif</p>
                            <p class="mt-2 break-words text-sm text-stone-600">Campaign: {{ $tags['utm_campaign'] ?? 'Not recorded' }}</p>
                            @if(isset($tags['utm_content']))<p class="mt-1 break-words text-xs text-stone-500">Content: {{ $tags['utm_content'] }}</p>@endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-stone-300 bg-white p-10 text-center"><h3 class="font-semibold text-stone-900">No café signups match these filters.</h3><p class="mt-2 text-sm text-stone-600">New café owners will appear here when they register.</p></div>
            @endforelse
        </div>
        {{ $merchants->links() }}
        <p class="text-xs leading-5 text-stone-500">Campaign attribution uses the first tagged visit carried into signup in the same browser session, for up to 30 days. Older accounts and untagged visits remain unattributed. Tags are visitor-supplied and are not proof of email delivery or identity.</p>
    </div>
</x-admin-layout>
