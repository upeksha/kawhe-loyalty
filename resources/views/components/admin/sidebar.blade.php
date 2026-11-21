@props(['navigationFilters' => []])
@php($navFilters = $navigationFilters ?? [])
<div class="ops-sidebar">
<a href="{{ route('admin.dashboard', $navFilters) }}" class="ops-sidebar-brand"><x-application-logo class="h-9 w-auto" /><span>Kawhe <span class="ops-subtle">Admin</span></span></a>
<p class="ops-sidebar-section">Workspace</p>
<nav aria-label="Admin navigation">
@foreach([
 ['admin.dashboard','Operations overview','overview','admin.dashboard'],
 ['admin.stores.index','Stores','stores','admin.stores.*'],
 ['admin.transactions.index','Transactions','activity','admin.transactions.*'],
 ['admin.issues.index','Needs attention','support','admin.issues.*'],
 ['admin.revenue.index','Plans & revenue','billing','admin.revenue.*'],
] as [$route,$label,$icon,$match])
<a href="{{ route($route, $navFilters) }}" @click="$dispatch('close-admin-sidebar')" @if(request()->routeIs($match)) aria-current="page" @endif><x-merchant-icon :name="$icon" />{{ $label }}</a>
@endforeach
</nav>
<p class="ops-sidebar-section">Existing tools</p>
<nav aria-label="Admin support tools">
@if(\Illuminate\Support\Facades\Route::has('admin.merchants.index'))<a href="{{ route('admin.merchants.index') }}">Café signups</a>@endif
<a href="{{ route('admin.support.index') }}" @click="$dispatch('close-admin-sidebar')" @if(request()->routeIs('admin.support.*')) aria-current="page" @endif><x-merchant-icon name="customers" />Support logs</a>
</nav>
<div class="ops-sidebar-account"><p>{{ auth()->user()->name }}</p><p class="ops-subtle">{{ auth()->user()->email }}</p><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out →</button></form></div>
</div>
