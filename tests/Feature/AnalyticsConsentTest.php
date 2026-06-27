<?php

/**
 * Guards the Google Analytics Consent Mode wiring in app.blade.php.
 *
 * Regression cover for the bug where `cookie_consent` was missing from the
 * EncryptCookies exception list: the plaintext JS cookie failed decryption,
 * the server read it as null, and the consent default was stuck on 'denied'
 * for everyone — so accepted users were never upgraded to 'granted' on first
 * byte.
 */
it('always loads gtag regardless of consent', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('googletagmanager.com/gtag/js?id=G-257R6ZLK1S', escape: false);
});

it('seeds analytics_storage granted when consent cookie is accepted', function () {
    // withUnencryptedCookie mirrors the plaintext cookie useCookieConsent sets
    // client-side; it only reaches the server because cookie_consent is in the
    // EncryptCookies exception list.
    $this->withUnencryptedCookie('cookie_consent', 'accepted')
        ->get('/login')
        ->assertSee("'analytics_storage': 'granted'", escape: false);
});

it('seeds analytics_storage denied when consent cookie is declined', function () {
    $this->withUnencryptedCookie('cookie_consent', 'declined')
        ->get('/login')
        ->assertSee("'analytics_storage': 'denied'", escape: false);
});

it('seeds analytics_storage denied when no consent cookie is present', function () {
    $this->get('/login')
        ->assertSee("'analytics_storage': 'denied'", escape: false);
});
