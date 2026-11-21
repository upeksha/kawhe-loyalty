<x-admin-layout :navigation-filters="$navigationFilters" header="Transactions"><div class="ops-page">
@include('admin.operations.filters',['searchLabel'=>'Search activity','searchPlaceholder'=>'Store, programme or team member'])
<div class="ops-page-intro"><h2>{{ number_format($events->total()) }} {{ \Illuminate\Support\Str::plural('transaction', $events->total()) }} @if($selectedStore) · {{ $selectedStore->name }} @endif</h2><p>One row per recorded transaction; quantity can be greater than one. Card IDs are internal references, not scannable codes.</p></div>
<section class="ops-panel">@include('admin.operations.events')<div class="ops-pagination">{{ $events->links() }}</div></section>
</div></x-admin-layout>
