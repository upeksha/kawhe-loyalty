<x-admin-layout :navigation-filters="$navigationFilters" header="Operations overview">
<div class="ops-page">
@include('admin.operations.filters')
<section class="ops-panel"><div class="ops-panel-heading"><div><h2>Plans & revenue</h2><p>Each café owner account counts once, even with several stores. Current plan status, all dates.</p></div><a class="ops-link" href="{{ route('admin.revenue.index',$navigationFilters) }}">View revenue →</a></div></section>
@include('admin.operations.plan-metrics')
<div class="ops-metrics">
@foreach([
 ['Active stores',$stats['stores'],'Current, not archived'], ['Loyalty programmes',$stats['programmes'],'Current card designs'],
 ['Unique customers',$stats['customers'],'Distinct customer records · all dates'], ['Issued cards',$stats['cards'],'Customer memberships · all dates'],
 ['New cards',$stats['new_cards'],'Joined during selected period'], ['Net stamps',$stats['net'],'Added minus undone · period'],
 ['Rewards redeemed',$stats['redeems'],'Quantity redeemed · period'], ['Available rewards',$stats['rewards_ready'],'Current unredeemed balance']
] as [$label,$value,$caption])<article class="ops-metric"><p>{{ $label }}</p><strong>{{ number_format($value) }}</strong><span>{{ $caption }}</span></article>@endforeach
</div>
<div class="ops-summary-line"><span><strong>{{ number_format($stats['stamps']) }}</strong> stamps added</span><span><strong>{{ number_format($stats['undos']) }}</strong> stamps undone</span><span><strong>{{ $stats['trading'] }}</strong> stores with transactions</span><span class="ops-subtle">Overview refreshed {{ \Carbon\Carbon::parse($stats['refreshed'])->timezone('Pacific/Auckland')->format('g:i:s a') }} · up to 60 seconds old</span></div>
<section class="ops-panel"><div class="ops-panel-heading"><div><h2>Loyalty activity</h2><p>Daily quantities in New Zealand time. Undo can reverse a stamp from an earlier day.</p></div><a class="ops-link" href="{{ route('admin.transactions.index', $navigationFilters) }}">Explore transactions →</a></div>
@php
 $maximum=max(1,...array_map(fn($p)=>max($p['stamp'],$p['stamp_undo'],$p['redeem']),$stats['trend']));
 $points=function($type) use($stats,$maximum) { $n=count($stats['trend']);return implode(' ',array_map(fn($p,$i)=>($n===1?450:20+$i*860/max(1,$n-1)).','.round(156-($p[$type]/$maximum)*136,2),$stats['trend'],array_keys($stats['trend']))); };
@endphp
@if($stats['stamps']+$stats['undos']+$stats['redeems']===0)<div class="ops-empty"><strong>No recorded activity in this period</strong><p>The chart will fill as customers collect stamps and use rewards.</p></div>@else
<div class="ops-chart" role="img" aria-label="Daily stamps added, stamp undos and rewards redeemed. Exact figures in the table below."><span class="ops-subtle">{{ number_format($maximum) }} units</span><svg viewBox="0 0 900 175" preserveAspectRatio="none"><path d="M20 20H880 M20 88H880 M20 156H880" stroke="#e7e5e4" stroke-dasharray="4 6" fill="none"/><polyline points="{{ $points('stamp') }}" stroke="#285540"/><polyline points="{{ $points('stamp_undo') }}" stroke="#bf8031"/><polyline points="{{ $points('redeem') }}" stroke="#527cb1"/>@if(count($stats['trend'])===1) @foreach(['stamp'=>'#285540','stamp_undo'=>'#bf8031','redeem'=>'#527cb1'] as $kind=>$color)<circle cx="450" cy="{{ 156-($stats['trend'][0][$kind]/$maximum)*136 }}" r="4" fill="{{ $color }}"/>@endforeach @endif</svg><div class="ops-chart-dates"><span>{{ $f['from'] }}</span><span>{{ $f['to'] }}</span></div></div>
@endif
<div class="ops-legend"><span><i style="background:#285540"></i>Stamps added</span><span><i style="background:#bf8031"></i>Undone</span><span><i style="background:#527cb1"></i>Rewards redeemed</span></div>
<details class="ops-chart-details"><summary>View daily figures</summary><div class="ops-table-scroll"><table class="ops-table"><thead><tr><th>NZ date</th><th>Added</th><th>Undone</th><th>Net stamps</th><th>Redeemed</th></tr></thead><tbody>@foreach($stats['trend'] as $point)<tr><td>{{ $point['date'] }}</td><td>{{ $point['stamp'] }}</td><td>{{ $point['stamp_undo'] }}</td><td>{{ $point['stamp']-$point['stamp_undo'] }}</td><td>{{ $point['redeem'] }}</td></tr>@endforeach</tbody></table></div></details>
</section>
<div class="ops-two-columns"><section class="ops-panel"><div class="ops-panel-heading"><div><h2>Needs attention</h2><p>Current queue and setup follow-up.</p></div><a class="ops-link" href="{{ route('admin.issues.index',$navigationFilters) }}">Review →</a></div><div class="ops-attention-grid">
<a href="{{ route('admin.issues.index',$navigationFilters) }}"><strong>{{ $queue['failed'] }}</strong><span>Failed jobs · all dates</span></a><a href="{{ route('admin.issues.index',$navigationFilters) }}"><strong>{{ $stats['issues'] }}</strong><span>Recorded issues · period</span></a><a href="{{ route('admin.issues.index',$navigationFilters) }}"><strong>{{ $stats['incomplete'] }}</strong><span>Unfinished store setups</span></a><a href="{{ route('admin.issues.index',$navigationFilters) }}"><strong>{{ $stats['unverified'] }}</strong><span>Owners awaiting verification</span></a>
</div><p class="ops-note">An unfinished signup is a follow-up opportunity, not an application error.</p></section><section class="ops-panel"><div class="ops-panel-heading"><div><h2>Merchant Issue Diagnostics</h2><p>Recent recorded failures. A later retry may have succeeded.</p></div></div>@include('admin.operations.issue-rows')</section></div>
<section class="ops-panel"><div class="ops-panel-heading"><div><h2>Recent Activity</h2><p>Latest transactions in the selected period.</p></div><a class="ops-link" href="{{ route('admin.transactions.index',$navigationFilters) }}">View all →</a></div>@include('admin.operations.events',['events'=>$recent])</section>
</div></x-admin-layout>
