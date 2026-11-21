<?php

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('store settings lists every active card with scoped customer counts and edit links', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $default = $store->resolvedDefaultProgram();
    $default->update(['name' => 'TEST Default Coffee']);
    $second = LoyaltyProgram::create(['store_id' => $store->id, 'name' => 'TEST Extra Pastry', 'reward_title' => 'Free pastry', 'reward_target' => 6]);
    $third = LoyaltyProgram::create(['store_id' => $store->id, 'name' => 'TEST Extra Tea', 'reward_title' => 'Free tea', 'reward_target' => 5]);
    $archived = LoyaltyProgram::create(['store_id' => $store->id, 'name' => 'TEST Archived Hidden', 'reward_title' => 'Archived reward', 'reward_target' => 4]);
    $archived->delete();
    $otherStore = Store::factory()->for($owner)->create();
    $otherStore->resolvedDefaultProgram()->update(['name' => 'TEST Other Store Hidden']);
    LoyaltyAccount::factory()->count(2)->create(['store_id' => $store->id, 'loyalty_program_id' => $second->id]);
    LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $third->id]);
    $before = [];
    foreach (['stores', 'loyalty_programs', 'loyalty_accounts'] as $table) {
        $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
    }
    $response = $this->actingAs($owner)->get(route('merchant.stores.edit', $store))->assertOk()->assertSee('id="customer-emails"', false)->assertSee('TEST Default Coffee')->assertSee('TEST Extra Pastry')->assertSee('TEST Extra Tea')->assertDontSee('TEST Archived Hidden')->assertDontSee('TEST Other Store Hidden');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//article[@data-store-card]')->length)->toBe(3);
    expect(substr_count($response->getContent(), 'Default card'))->toBe(1);
    foreach ([$default->id => 0, $second->id => 2, $third->id => 1] as $id => $count) {
        $card = $xpath->query('//article[@data-store-card="'.$id.'"]')->item(0);
        expect($xpath->query('.//dt[text()="Customers"]/following-sibling::dd', $card)->item(0)->textContent)->toBe((string) $count);
        expect($xpath->query('.//a[@href="'.route('merchant.stores.programs.edit', [$store, $id]).'"]', $card)->length)->toBe(1);
    }
    foreach ($before as $table => $rows) {
        expect(DB::table($table)->orderBy('id')->get()->toJson())->toBe($rows);
    }
    $anotherOwner = User::factory()->create();
    Store::factory()->for($anotherOwner)->create();
    $this->actingAs($anotherOwner)->get(route('merchant.stores.edit', $store))->assertNotFound();
});

test('archived store lists its preserved cards without offering unavailable edit actions', function () {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    Store::factory()->for($store->user)->create();
    $store->delete();

    $this->actingAs($store->user)->get(route('merchant.stores.edit', $store))->assertOk()
        ->assertSee($program->name)->assertSee('Restore store')
        ->assertDontSee('aria-label="Edit '.$program->name.'"', false)
        ->assertDontSee('id="customer-emails"', false);
});
