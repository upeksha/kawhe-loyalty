<?php

use App\Models\Store;

function joinPageDocument($response): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());

    return new DOMXPath($document);
}

test('both join entry pages expose the same signup and keep required fields outside optional details', function () {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['registration_form_config' => [
        'first_name' => ['enabled' => true, 'required' => true],
        'last_name' => ['enabled' => false, 'required' => false],
        'phone' => ['enabled' => true, 'required' => false],
        'birthday' => ['enabled' => true, 'required' => false],
    ]]);
    foreach (['join.index', 'join.show'] as $route) {
        $response = $this->get(route($route, ['slug' => $program->slug, 't' => $program->join_token]))->assertOk();
        $xpath = joinPageDocument($response);
        expect($xpath->query('//form[@id="join-form"]')->length)->toBe(1);
        expect($xpath->query('//form[@id="join-form"]')->item(0)->getAttribute('action'))->toBe(route('join.store', ['slug' => $program->slug, 't' => $program->join_token]));
        expect($xpath->query('//input[@name="_token"]')->length)->toBe(1);
        expect($xpath->query('//input[@id="first_name" and @required and not(ancestor::details)]')->length)->toBe(1);
        expect($xpath->query('//input[@id="phone" and not(@required) and ancestor::details]')->length)->toBe(1);
        expect($xpath->query('//input[@id="birthday" and not(@required) and ancestor::details]')->length)->toBe(1);
        expect($xpath->query('//input[@id="last_name"]')->length)->toBe(0);
        $response->assertSee('Recover an existing card')->assertSee('Privacy')->assertSee('Support');
    }
});

test('invalid optional input stays visible with customer entries preserved', function () {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['registration_form_config' => [
        'birthday' => ['enabled' => true, 'required' => false],
        'first_name' => ['enabled' => true, 'required' => false],
    ]]);
    $url = route('join.index', ['slug' => $program->slug, 't' => $program->join_token]);
    $this->from($url)->post(route('join.store', ['slug' => $program->slug, 't' => $program->join_token]), [
        'email' => 'invalid', 'first_name' => 'Alex', 'birthday' => 'not-a-date',
    ])->assertSessionHasErrors(['email', 'birthday']);
    $response = $this->get($url)->assertOk();
    $xpath = joinPageDocument($response);
    expect($xpath->query('//input[@id="birthday"]/ancestor::details[@open]')->length)->toBe(1);
    expect($xpath->query('//input[@id="first_name"]')->item(0)->getAttribute('value'))->toBe('Alex');
    expect($xpath->query('//input[@id="email"]')->item(0)->getAttribute('value'))->toBe('invalid');
});

test('email only signup has no optional details section', function () {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['registration_form_config' => []]);
    $this->get(route('join.index', ['slug' => $program->slug, 't' => $program->join_token]))
        ->assertOk()->assertSee('Create my card')->assertDontSee('Add optional details');
});
