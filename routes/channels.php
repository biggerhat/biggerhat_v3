<?php

use App\Models\Campaign\Campaign;
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

// Campaign real-time updates (T3-34) — any campaign member (organizer or a
// joined player) may listen, same membership check as
// AuthorizesCampaignAccess::ensureCampaignMember().
Broadcast::channel('campaign.{id}', function ($user, $id) {
    $campaign = Campaign::find($id);
    if (! $campaign) {
        return false;
    }

    $isMember = $user->hasRole('super_admin')
        || $campaign->players()->where('user_id', $user->id)->exists();

    return $isMember ? ['id' => $user->id, 'name' => $user->name] : false;
});
