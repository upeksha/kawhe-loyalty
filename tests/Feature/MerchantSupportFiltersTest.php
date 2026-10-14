<?php

use App\Models\Store;
use App\Models\SupportAuditLog;
use App\Models\User;

test('support filters and pagination preserve scoped results and matching totals', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $otherStore = Store::factory()->create();
    foreach (range(1, 26) as $i) {
        SupportAuditLog::create(['store_id' => $store->id, 'event_type' => 'wallet_sync', 'status' => 'failed', 'message' => 'Owned wallet failure']);
    }
    SupportAuditLog::create(['store_id' => $store->id, 'event_type' => 'verification_send', 'status' => 'success', 'message' => 'Nonmatching success']);
    SupportAuditLog::create(['store_id' => $otherStore->id, 'event_type' => 'wallet_sync', 'status' => 'failed', 'message' => 'Foreign wallet failure']);
    $filters = ['store_id' => $store->id, 'event_type' => 'wallet_sync', 'status' => 'failed'];
    $page = $this->actingAs($owner)->get(route('merchant.support.index', $filters))->assertOk();
    $page->assertSee('Owned wallet failure')->assertDontSee('Nonmatching success')->assertDontSee('Foreign wallet failure');
    $page->assertViewHas('summary', ['total' => 26, 'failed' => 26, 'actionable' => 26]);
    $page->assertViewHas('logs', function ($logs) use ($filters) {
        parse_str(parse_url($logs->nextPageUrl(), PHP_URL_QUERY), $query);

        return $logs->count() === 25 && $query['page'] === '2'
            && $query['store_id'] === (string) $filters['store_id'] && $query['event_type'] === 'wallet_sync' && $query['status'] === 'failed';
    });
    $this->get(route('merchant.support.index', $filters + ['page' => 2]))->assertOk()->assertViewHas('logs', fn ($logs) => $logs->count() === 1);
    $this->get(route('merchant.support.index', ['q' => 'no-match']))->assertOk()->assertSee('No matching support events')->assertSee('Clear filters');
});
