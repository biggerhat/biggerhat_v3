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

it('counts games logged by hand, keeps private ones to their owner and can leave out solo games', function () {
    $user = User::factory()->create();

    $tracked = Game::factory()->completed()->create(['creator_id' => $user->id, 'winner_id' => $user->id]);
    GamePlayer::factory()->create(['game_id' => $tracked->id, 'user_id' => $user->id, 'slot' => 1]);
    GamePlayer::factory()->opponent()->create(['game_id' => $tracked->id]);

    $solo = Game::factory()->completed()->create(['creator_id' => $user->id, 'is_solo' => true, 'winner_slot' => 1]);
    GamePlayer::factory()->create(['game_id' => $solo->id, 'user_id' => $user->id, 'slot' => 1]);
    GamePlayer::factory()->soloOpponent()->create(['game_id' => $solo->id]);

    foreach ([false, true] as $public) {
        $logged = Game::factory()->logged()->create(['creator_id' => $user->id, 'winner_slot' => 2, 'is_public' => $public]);
        GamePlayer::factory()->create(['game_id' => $logged->id, 'user_id' => $user->id, 'slot' => 1]);
        GamePlayer::factory()->soloOpponent()->create(['game_id' => $logged->id]);
    }

    // the owner sees all four, a visitor everything but the private logged game
    $this->actingAs($user)->get(route('stats.show', $user->slug))->assertInertia(fn ($p) => $p->where('stats.total_games', 4)->where('stats.losses', 2)->where('logged_games', 2));
    $this->actingAs(User::factory()->create())->get(route('stats.show', $user->slug))->assertInertia(fn ($p) => $p->where('stats.total_games', 3)->where('logged_games', 1));
    $this->actingAs($user)->get(route('stats.show', ['user' => $user->slug, 'solo' => 0]))->assertInertia(fn ($p) => $p->where('stats.total_games', 3)->where('with_solo', false));
});

it('lists the games logged against you that wait for your confirmation, to you only', function () {
    $me = User::factory()->create();
    $sam = User::factory()->create();
    $game = Game::factory()->logged()->create(['creator_id' => $me->id, 'winner_slot' => 1, 'winner_id' => $me->id]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $me->id, 'slot' => 1]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => null, 'slot' => 2, 'linked_user_id' => $sam->id, 'link_status' => 'pending', 'opponent_name' => 'Sam']);

    $this->actingAs($sam)->get(route('stats.show', $sam->slug))->assertInertia(fn ($p) => $p->where('stats.total_games', 0)->has('awaiting_confirmation', 1)->where('awaiting_confirmation.0.by', $me->name));
    $this->actingAs($me)->get(route('stats.show', $sam->slug))->assertInertia(fn ($p) => $p->where('awaiting_confirmation', []));
});
