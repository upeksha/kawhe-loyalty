        <nav aria-label="Merchant navigation" class="grid grid-cols-2 gap-2 sm:grid-cols-5 xl:grid-cols-10">
            @foreach([
                ['Overview', route('merchant.dashboard'), request()->routeIs('merchant.dashboard')],
                ['Cards', route('merchant.programs.index'), request()->routeIs('merchant.programs.*', 'merchant.stores.programs.*')],
                ['Customers', route('merchant.customers.index'), false],
                ['Team', route('merchant.staff.index'), false],
                ['Activity', route('merchant.support.index'), false],
                ['Billing', route('billing.index'), false],
                ['Settings', route('profile.edit'), false],
                ['Stores', route('merchant.stores.index'), request()->routeIs('merchant.stores.*') && !request()->routeIs('merchant.stores.programs.*')],
                ['Support', route('merchant.support.index'), false],
            ] as [$label, $url, $active])
                <a href="{{ $url }}" @if($active) aria-current="page" @endif class="dashboard-button flex items-center justify-center rounded-lg border border-stone-200 px-3 text-sm font-semibold text-brand-700 {{ $active ? 'bg-brand-50' : 'bg-white hover:bg-brand-50' }}">{{ $label }}</a>
            @endforeach
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="dashboard-button w-full rounded-lg bg-white px-3 text-sm font-semibold text-brand-700 hover:bg-brand-50">Sign out</button></form>
        </nav>
