        <nav aria-label="Merchant navigation" class="grid grid-cols-2 gap-2 sm:grid-cols-5 xl:grid-cols-10">
            @foreach(auth()->user()->is_staff_account ? [
                ['Scanner', route('merchant.scanner'), request()->routeIs('merchant.scanner')],
                ['Settings', route('profile.edit'), request()->routeIs('profile.*')],
            ] : [
                ['Overview', route('merchant.dashboard'), request()->routeIs('merchant.dashboard')],
                ['Cards', route('merchant.programs.index'), request()->routeIs('merchant.programs.*', 'merchant.stores.programs.*')],
                ['Customers', route('merchant.customers.index'), request()->routeIs('merchant.customers.*')],
                ['Team', route('merchant.staff.index'), request()->routeIs('merchant.staff.*')],
                ['Activity', route('merchant.activity.index'), request()->routeIs('merchant.activity.*')],
                ['Billing', route('billing.index'), request()->routeIs('billing.*')],
                ['Settings', route('profile.edit'), request()->routeIs('profile.*')],
                ['Stores', route('merchant.stores.index'), request()->routeIs('merchant.stores.*') && !request()->routeIs('merchant.stores.programs.*')],
                ['Support', route('merchant.support.index'), request()->routeIs('merchant.support.*')],
            ] as [$label, $url, $active])
                <a href="{{ $url }}" @if($active) aria-current="page" @endif class="dashboard-button flex items-center justify-center rounded-lg border border-stone-200 px-3 text-sm font-semibold text-brand-700 {{ $active ? 'bg-brand-50' : 'bg-white hover:bg-brand-50' }}">{{ $label }}</a>
            @endforeach
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="dashboard-button w-full rounded-lg bg-white px-3 text-sm font-semibold text-brand-700 hover:bg-brand-50">Sign out</button></form>
        </nav>
