<?php

use App\Models\Game;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('game.{uuid}', function ($user, $uuid) {
    $game = Game::where('uuid', $uuid)->first();
    if (! $game) {
        return false;
    }

    $isParticipant = $game->creator_id === $user->id
        || $game->players()->where('user_id', $user->id)->exists();

    return $isParticipant ? ['id' => $user->id, 'name' => $user->name] : false;
});
