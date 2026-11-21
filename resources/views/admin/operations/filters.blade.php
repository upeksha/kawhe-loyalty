@php
    $configuredHost = parse_url(config('app.url'), PHP_URL_HOST);
    $environmentLabel = in_array($configuredHost, ['127.0.0.1', 'localhost']) ? 'LOCAL' : (($configuredHost === 'app.kawhe.shop' && app()->environment('production')) ? 'LIVE' : 'STAGING / SANDBOX');
@endphp
<div class="ops-context">
    <div><span class="ops-environment">{{ $environmentLabel }}</span><span class="ops-subtle">Read-only reporting · New Zealand time</span></div>
    <p class="ops-subtle">{{ \Carbon\Carbon::parse($f['from'])->format('j M Y') }} – {{ \Carbon\Carbon::parse($f['to'])->format('j M Y') }}</p>
</div>
<form method="GET" action="{{ url()->current() }}" class="ops-filterbar" x-data="{}">
    <div><label for="ops-range">Period</label><select id="ops-range" name="range" @change="$refs.from.value = ''; $refs.to.value = ''">@foreach(['today'=>'Today', '7d'=>'Last 7 days', '30d'=>'Last 30 days', '90d'=>'Last 90 days'] as $value=>$label)<option value="{{ $value }}" @selected($f['range'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="ops-scope">Stores included</label><select name="scope" id="ops-scope">@foreach(['customers'=>'Customer stores', 'all'=>'All stores', 'test'=>'Test stores'] as $value=>$label)<option value="{{ $value }}" @selected($f['scope'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="ops-from">From <span class="ops-subtle">optional</span></label><input x-ref="from" type="date" name="from" id="ops-from" value="{{ request('from') }}"></div>
    <div><label for="ops-to">To <span class="ops-subtle">optional</span></label><input x-ref="to" type="date" name="to" id="ops-to" value="{{ request('to') }}" max="{{ now('Pacific/Auckland')->toDateString() }}"></div>
    @if(isset($searchLabel))<div class="ops-filter-search"><label for="ops-search">{{ $searchLabel }}</label><input name="q" id="ops-search" value="{{ $f['q'] }}" maxlength="100" placeholder="{{ $searchPlaceholder ?? 'Search…' }}"></div>@endif
    @if(request()->routeIs('admin.stores.index'))<div><label for="ops-status">Store status</label><select name="status" id="ops-status">@foreach(['active'=>'Active', 'archived'=>'Archived', 'all'=>'All statuses'] as $value=>$label)<option value="{{ $value }}" @selected($f['status']===$value)>{{ $label }}</option>@endforeach</select></div>@endif
    @if(request()->routeIs('admin.transactions.index'))<div><label for="ops-type">Transaction</label><select name="type" id="ops-type">@foreach([''=>'All types','stamp'=>'Stamps added','stamp_undo'=>'Stamp undo','redeem'=>'Reward redeemed'] as $value=>$label)<option value="{{ $value }}" @selected($f['type']===$value)>{{ $label }}</option>@endforeach</select></div>@endif
    @if($f['store_id'])<input type="hidden" name="store_id" value="{{ $f['store_id'] }}">@endif
    <div class="ops-filter-actions"><button class="ops-button" type="submit">Apply filters</button><a class="ops-link" href="{{ request()->routeIs('admin.stores.show') ? url()->current().'?scope='.$f['scope'] : url()->current() }}">Reset</a></div>
</form>
<p class="ops-note">Optional dates override the period. Reports are limited to 90 days.</p>
@if(!config('admin_operations.test_store_ids'))<p class="ops-note">Test-store classification is not configured. Customer scope currently includes all stores; no records are guessed or hidden by name.</p>@endif
@if($errors->any())<div class="ops-notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

@if(config('admin_operations.test_store_ids') && $f['scope']==='customers')<p class="ops-note">Excludes {{ count(config('admin_operations.test_store_ids')) }} explicitly classified test stores. Other stores remain included until classified.</p>@endif
