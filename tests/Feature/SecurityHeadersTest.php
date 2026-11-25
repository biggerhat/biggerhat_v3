<?php

it('sends the browser-hardening headers on web pages', function () {
    $response = $this->get('/privacy');

    $response->assertOk();
    expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()', 'microphone=()', 'geolocation=()');
});

it('sends Strict-Transport-Security only over HTTPS', function () {
    expect($this->get('/privacy')->headers->has('Strict-Transport-Security'))->toBeFalse();

    $secure = $this->get('https://localhost/privacy');
    expect($secure->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000');
});

it('sends them on an error response too', function () {
    $response = $this->get('/this-page-does-not-exist');

    $response->assertNotFound();
    expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});
