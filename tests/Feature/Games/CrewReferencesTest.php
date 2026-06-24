<?php

use App\Models\Character;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Strategy;
use App\Models\Token;
use App\Models\User;

it("augments a player's references with general tokens, strategy tokens, and live crew", function () {
    Token::factory()->create(['name' => 'Focus', 'is_general' => true]);
    $stratToken = Token::factory()->create(['name' => 'Explosive']);
    $strategy = Strategy::factory()->create(['name' => 'Plant Explosives']);
    $strategy->tokens()->attach($stratToken->id);

    $charToken = Token::factory()->create(['name' => 'Burning']);
    $character = Character::factory()->create();
    $character->tokens()->attach($charToken->id);

    $user = User::factory()->create();
    $game = Game::factory()->inProgress()->create([
        'creator_id' => $user->id,
        'strategy_id' => $strategy->id,
        'is_solo' => true,
    ]);
    $player = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);
    GameCrewMember::factory()->create([
        'game_id' => $game->id,
        'game_player_id' => $player->id,
        'character_id' => $character->id,
    ]);

    $this->actingAs($user)->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('game.players', function ($players) {
            $names = collect(collect($players)->firstWhere('slot', 1)['references']['tokens'] ?? [])->pluck('name');

            return $names->contains('Focus')        // general token
                && $names->contains('Explosive')    // strategy token
                && $names->contains('Burning');     // live crew member's token
        }));
});

it('does not surface unrelated tokens', function () {
    Token::factory()->create(['name' => 'Unrelated', 'is_general' => false]);

    $user = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $user->id, 'is_solo' => true]);
    $player = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);
    GameCrewMember::factory()->create(['game_id' => $game->id, 'game_player_id' => $player->id]);

    $this->actingAs($user)->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('game.players', function ($players) {
            $names = collect(collect($players)->firstWhere('slot', 1)['references']['tokens'] ?? [])->pluck('name');

            return ! $names->contains('Unrelated');
        }));
});
