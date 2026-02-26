<?php

test('api login endpoint is rate limited', function () {
    $payload = [
        'email' => 'nobody@example.com',
        'password' => 'invalid-password',
    ];

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);
});
