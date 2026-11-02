<?php

it('applies the saved reader text size to the first paint', function (string $size) {
    $this->withUnencryptedCookie('text_size', $size)
        ->get(route('privacy'))
        ->assertOk()
        ->assertSee('data-text-size="'.$size.'"', false);
})->with(['large', 'xlarge']);

it('ignores unknown text sizes and the default size', function (?string $size) {
    $response = $size === null ? $this->get(route('privacy')) : $this->withUnencryptedCookie('text_size', $size)->get(route('privacy'));

    $response->assertOk()->assertDontSee('data-text-size', false);
})->with([null, 'normal', 'huge', '"><script>']);

it('applies a valid accent theme from the cookie and rejects anything else', function () {
    $this->withUnencryptedCookie('theme', 'guild')->get(route('privacy'))->assertSee('data-theme="guild"', false);
    $this->withUnencryptedCookie('theme', 'not-a-theme')->get(route('privacy'))->assertDontSee('data-theme=', false);
});
