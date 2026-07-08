<?php

use App\Enums\GameStatusEnum;
use App\Models\CrewBuild;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\User;

it('redirects my-stats to the authenticated user\'s stats page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('stats.my'))
        ->assertRedirect(route('stats.show', $user->slug));
});

it('renders empty stats for a user with no completed games', function () {
    $user = User::factory()->create();

    $this->get(route('stats.show', $user->slug))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Profile/Stats')
            ->where('stats.total_games', 0)
            ->where('profile.badges', [])
        );
});

it('computes win/loss record, tournament record, and badges for a user with activity', function () {
    $user = User::factory()->create();

    $game = Game::factory()->create(['status' => GameStatusEnum::Completed, 'is_solo' => false, 'winner_id' => $user->id]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);
    GamePlayer::factory()->opponent()->create(['game_id' => $game->id]);

    $tournament = Tournament::factory()->create(['status' => \App\Enums\TournamentStatusEnum::Completed]);
    TournamentPlayer::factory()->create(['tournament_id' => $tournament->id, 'user_id' => $user->id]);

    CrewBuild::factory()->create(['user_id' => $user->id, 'is_public' => true, 'is_archived' => false]);

    $this->get(route('stats.show', $user->slug))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Profile/Stats')
            ->where('stats.total_games', 1)
            ->where('stats.wins', 1)
            ->where('profile.tournaments_played', 1)
            ->where('profile.public_crews', 1)
            ->has('profile.badges')
        );
});
