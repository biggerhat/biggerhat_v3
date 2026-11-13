<?php

it('ships the start-up watchdog in the page shell', function () {
    $this->get('/')->assertOk()->assertSee('bh-watchdog-reloaded', false)->assertSee('__appMounted', false);
});
