<div class="ops-issue-list">
@forelse($issues as $issue)<article class="ops-issue"><span class="ops-issue-dot" aria-hidden="true"></span><div><strong>{{ $report->issueLabel($issue->event_type) }}</strong><p>@if($issue->store)<a class="ops-link" href="{{ route('admin.stores.show', ['store'=>$issue->store_id,...$navigationFilters]) }}">{{ $issue->store->name }}</a>@else Platform / unavailable store @endif</p><p class="ops-subtle">{{ $issue->created_at->timezone('Pacific/Auckland')->format('j M, g:i a') }} · {{ str_replace('_',' ', $issue->status) }} · Event #{{ $issue->id }}</p></div></article>
@empty<div class="ops-empty"><strong>No recorded support failures in this period</strong><p>This covers recorded support events, not every application or provider error.</p></div>@endforelse
</div>
