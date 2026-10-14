<?php

use App\Models\Store;
use App\Models\User;

function workspaceDom(string $html): DOMXPath
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($document);
}

test('customer search and store selection share one form and independent clear links preserve the other filter', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $response = $this->actingAs($owner)->get(route('merchant.customers.index', ['q' => 'Alex', 'store_id' => $store->id]))->assertOk();
    $dom = workspaceDom($response->getContent());
    $form = $dom->query('//form[@aria-label="Filter customers"]')->item(0);
    expect($dom->query('.//input[@name="q" and @value="Alex"]', $form)->length)->toBe(1);
    expect($dom->query('.//select[@name="store_id"]/option[@selected and @value="'.$store->id.'"]', $form)->length)->toBe(1);
    foreach (['Remove search filter' => ['store_id' => (string) $store->id], 'Remove store filter' => ['q' => 'Alex']] as $label => $expected) {
        $link = $dom->query('//a[@aria-label="'.$label.'"]')->item(0);
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        expect($query)->toBe($expected);
    }
    expect($dom->query('//nav[@aria-label="Merchant navigation"]//a[@aria-current="page"]')->length)->toBe(1);
});

test('loyalty card editor keeps only loyalty cards active in the shared navigation', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $program = $store->resolvedDefaultProgram();
    $response = $this->actingAs($owner)->get(route('merchant.stores.programs.edit', [$store, $program]))->assertOk();
    $dom = workspaceDom($response->getContent());
    $active = $dom->query('//nav[@aria-label="Merchant navigation"]//a[@aria-current="page"]');
    expect($active->length)->toBe(1)->and($active->item(0)->getAttribute('href'))->toBe(route('merchant.programs.index'));
});

test('staff scanner navigation does not expose owner management pages', function () {
    $staff = User::factory()->create(['is_staff_account' => true]);
    $response = $this->actingAs($staff)->get(route('merchant.scanner'))->assertOk();
    $dom = workspaceDom($response->getContent());
    $links = $dom->query('//nav[@aria-label="Merchant navigation"]//a');
    expect($links->length)->toBe(2);
    expect($links->item(0)->getAttribute('href'))->toBe(route('merchant.scanner'));
    expect($links->item(1)->getAttribute('href'))->toBe(route('profile.edit'));
});
