<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('index', absolute: false));
});

test('registration is rate limited per client', function () {
    foreach (range(1, 10) as $i) {
        $this->post('/register', ['name' => '', 'email' => '', 'password' => '', 'password_confirmation' => '']);
    }

    $this->post('/register', ['name' => '', 'email' => '', 'password' => '', 'password_confirmation' => ''])->assertStatus(429);
});
