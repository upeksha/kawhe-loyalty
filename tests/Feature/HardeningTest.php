<?php

test('home page leads to a working sign in page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
    $this->get(route('login'))->assertOk()->assertSee('Sign in');
});
