@php
    $staff = auth()->user()->is_staff_account;
    $groups = $staff ? ['Workspace' => [
        ['Scanner', 'merchant.scanner', request()->routeIs('merchant.scanner'), 'scan'],
        ['Account settings', 'profile.edit', request()->routeIs('profile.*'), 'settings'],
    ]] : [
        'Workspace' => [
            ['Overview', 'merchant.dashboard', request()->routeIs('merchant.dashboard'), 'overview'],
            ['Loyalty cards', 'merchant.programs.index', request()->routeIs('merchant.programs.*', 'merchant.stores.programs.*'), 'cards'],
            ['Customers', 'merchant.customers.index', request()->routeIs('merchant.customers.*'), 'customers'],
            ['Activity', 'merchant.activity.index', request()->routeIs('merchant.activity.*'), 'activity'],
        ],
        'Manage' => [
            ['Stores', 'merchant.stores.index', request()->routeIs('merchant.stores.*') && !request()->routeIs('merchant.stores.programs.*'), 'stores'],
            ['Team', 'merchant.staff.index', request()->routeIs('merchant.staff.*'), 'team'],
            ['Billing', 'billing.index', request()->routeIs('billing.*'), 'billing'],
            ['Settings', 'profile.edit', request()->routeIs('profile.*'), 'settings'],
        ],
        'Help' => [['Support', 'merchant.support.index', request()->routeIs('merchant.support.*'), 'support']],
    ];
@endphp
<nav aria-label="Merchant navigation" class="workspace-nav">
    @foreach($groups as $group => $items)
        <div class="workspace-nav-group">
            <p class="workspace-nav-label">{{ $group }}</p>
            @foreach($items as [$label, $route, $active, $icon])
                <a href="{{ route($route) }}" @if($active) aria-current="page" @endif class="workspace-nav-link"><x-merchant-icon :name="$icon" /><span>{{ $label }}</span></a>
            @endforeach
        </div>
    @endforeach
</nav>
