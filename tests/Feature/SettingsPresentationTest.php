<?php

use App\Models\User;

function settingsDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML($html);

    return new DOMXPath($document);
}

test('staff settings expose profile and scanner but no owner navigation or billing controls', function () {
    $staff = User::factory()->create(['is_staff_account' => true]);
    $page = $this->actingAs($staff)->get(route('profile.edit'))->assertOk();
    $page->assertSee(route('merchant.scanner'))->assertSee('Save profile')->assertSee('Change password');
    $page->assertDontSee(route('billing.index'))->assertDontSee(route('merchant.staff.index'))->assertDontSee(route('merchant.customers.index'));
});

test('password validation returns to an expanded section with the error', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->from(route('profile.edit'))->put(route('password.update'), [
        'current_password' => 'incorrect', 'password' => 'new-test-password', 'password_confirmation' => 'new-test-password',
    ])->assertSessionHasErrorsIn('updatePassword', 'current_password');
    $page = $this->get(route('profile.edit'))->assertOk();
    $dom = settingsDocument($page->getContent());
    expect($dom->query('//details[@id="change-password" and @open]')->length)->toBe(1);
    $page->assertSee('The password is incorrect.');
});

test('deletion validation returns to an expanded review with its confirmation and error', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->from(route('profile.edit'))->delete(route('profile.destroy'), ['password' => 'incorrect'])
        ->assertSessionHasErrorsIn('userDeletion', 'password');
    $page = $this->get(route('profile.edit'))->assertOk();
    $dom = settingsDocument($page->getContent());
    expect($dom->query('//details[@id="delete-account" and @open]')->length)->toBe(1);
    $page->assertSee('The password is incorrect.')->assertSee('Cancel any paid subscription');
    expect($user->fresh())->not->toBeNull();
});
