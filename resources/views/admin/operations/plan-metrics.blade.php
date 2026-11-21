<div class="ops-metrics">
@foreach([['Free accounts',$plans['free'],'Current Free access'],['Pro accounts',$plans['pro'],'Active and trialling Pro access'],['Active Pro accounts',$plans['active'],'Active subscription · not a payment receipt'],['Pro trials',$plans['trialing'],'Trialling · excluded from revenue estimate']] as [$label,$value,$caption])
<article class="ops-metric"><p>{{ $label }}</p><strong>{{ number_format($value) }}</strong><span>{{ $caption }}</span></article>
@endforeach
</div>
