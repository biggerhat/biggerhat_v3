<?php

use App\Support\Game\SafeBroadcast;
use Illuminate\Broadcasting\BroadcastException;

it('swallows a broadcast failure so the saved action still succeeds', function () {
    $ran = false;
    SafeBroadcast::send(function () use (&$ran) {
        $ran = true;
        throw new BroadcastException('cURL error 7: Failed to connect to localhost port 8080');
    });

    expect($ran)->toBeTrue();
});

it('lets any other error through', function () {
    SafeBroadcast::send(fn () => throw new RuntimeException('a real bug'));
})->throws(RuntimeException::class);
