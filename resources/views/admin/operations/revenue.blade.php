<x-admin-layout :navigation-filters="$navigationFilters" header="Plans & revenue">
<div class="ops-page">
@include('admin.operations.filters')
@if($f['store_id'])<p class="ops-note">Account for store: <strong>{{ $selectedStore?->name ?? 'Outside the selected scope' }}</strong> · <a class="ops-link" href="{{ route('admin.revenue.index',$navigationFilters) }}">Show all accounts in this scope</a></p>@endif
<div class="ops-notice"><strong>Account-based billing</strong>Each owner with a store in the selected scope counts once, including archived stores. Staff logins are excluded. Owners with both customer and test stores can appear in both scopes. Plan counts are current, regardless of the date filter; only paid invoices use the selected period. Local subscription fixtures do not prove a payment was received.</div>
@include('admin.operations.plan-metrics')
<div class="ops-summary-line"><span><strong>{{ $plans['accounts'] }}</strong> owner accounts</span><span><strong>{{ $plans['past_due'] }}</strong> past due</span><span><strong>{{ $plans['cancelling'] }}</strong> Pro cancellations scheduled</span><span class="ops-subtle">Plan counts cached for up to 60 seconds</span></div>
<section class="ops-panel"><div class="ops-panel-heading"><div><h2>Subscription revenue</h2><p>{{ $revenue['mode']==='test' ? 'Sandbox amounts only — no real earnings.' : 'Stripe paid invoices for known Kawhe subscriptions.' }}</p></div><span class="ops-badge">{{ $revenue['mode']==='test' ? 'TEST DATA' : 'LIVE STRIPE' }}</span></div>
@if($revenue['state']==='ready')
<div class="ops-programme-grid">
<article class="ops-programme"><div><h3>Paid invoice total</h3>@forelse($revenue['paid'] as $currency=>$amount)<p><strong>{{ strtoupper($currency) }} {{ \Laravel\Cashier\Cashier::formatAmount($amount,$currency) }}</strong></p>@empty<p>No paid invoices in this period.</p>@endforelse<p>{{ $revenue['invoices'] }} invoices paid during the selected NZ dates. Includes tax; before refunds, credits and Stripe fees. Not profit or bank payouts.</p></div></article>
<article class="ops-programme"><div><h3>Estimated monthly recurring revenue</h3>@forelse($revenue['mrr'] as $currency=>$amount)<p><strong>{{ strtoupper($currency) }} {{ \Laravel\Cashier\Cashier::formatAmount((int)round($amount),$currency) }} / month</strong></p>@empty<p>No priced active subscriptions to estimate.</p>@endforelse<p>Current active subscription list prices and quantities, normalised monthly. Excludes trials; before discounts, tax and fees. Not collected income.</p>@if($revenue['unknown'])<p>{{ $revenue['unknown'] }} active subscriptions could not be priced; estimate is incomplete.</p>@endif</div></article>
</div>
<p class="ops-note">Stripe snapshot {{ \Carbon\Carbon::parse($revenue['refreshed'])->timezone('Pacific/Auckland')->format('j M, g:i a') }} · cached up to 10 minutes. Currencies are kept separate.</p>
@else
<div class="ops-empty"><strong>{{ match($revenue['state']) {'too_many'=>'Revenue report exceeds the safe scan limit','unavailable'=>'Stripe revenue is temporarily unavailable',default=>'Stripe revenue reporting is not connected'} }}</strong><p>Amounts are unavailable, not zero. Plan counts above still use Kawhe’s subscription records.</p><p>{{ $revenue['state']==='not_connected' ? 'Read-only Stripe reporting must be enabled for this environment. Local development blocks live Stripe keys.' : 'No partial monetary totals are displayed. Billing and customer cards continue to work independently.' }}</p></div>
@endif
</section>
<section class="ops-panel"><div class="ops-panel-heading"><div><h2>Owner accounts</h2><p>Current plan access from the same billing rules used by the app.</p></div></div><div class="ops-table-scroll"><table class="ops-table"><thead><tr><th>Account</th><th>Plan</th><th>Subscription status</th><th>Access end · NZ</th></tr></thead><tbody>
@forelse($owners as $owner)@php($sub=$owner->subscription('default'))<tr><td><strong>{{ $owner->name }}</strong><div class="ops-subtle">{{ $owner->email }}</div></td><td><span class="ops-badge">{{ $usage->planLabel($owner) }}</span></td><td>{{ $sub ? str_replace('_',' ',$sub->stripe_status) : 'No subscription' }}</td><td>{{ $sub?->ends_at?->timezone('Pacific/Auckland')->format('j M Y') ?? '—' }}</td></tr>@empty<tr><td colspan="4">No owner accounts in this store scope.</td></tr>@endforelse
</tbody></table></div><div class="ops-pagination">{{ $owners->links() }}</div></section>
</div>
</x-admin-layout>
