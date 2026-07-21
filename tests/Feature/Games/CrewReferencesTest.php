<?php

use App\Enums\FactionEnum;
use App\Enums\GameFormatEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCardAdvancement;
use App\Models\Campaign\CampaignGame;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Strategy;
use App\Models\Token;
use App\Models\Upgrade;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

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

it("surfaces a Campaign crew's Crew Card Tier-4 Upgrade borrow (and starter, when Upgrade-sourced) in references.upgrades", function () {
    $userA = User::factory()->create(['email_verified_at' => now()]);
    $userA->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $userB = User::factory()->create(['email_verified_at' => now()]);
    $userB->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    $campaign = Campaign::factory()->active()->create(['organizer_user_id' => $userA->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $userA->id]);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userB->id]);
    $crewA = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userA->id, 'faction' => FactionEnum::Arcanists->value]);
    $crewB = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userB->id, 'faction' => FactionEnum::Guild->value]);

    $borrowedUpgrade = Upgrade::factory()->create(['name' => 'Borrowed Crew Card Upgrade']);
    CampaignCrewCardAdvancement::factory()->create([
        'campaign_crew_id' => $crewA->id,
        'crew_card_effect_id' => $borrowedUpgrade->id,
        'crew_card_effect_type' => Upgrade::class,
    ]);

    $game = Game::factory()->inProgress()->create(['format' => GameFormatEnum::Campaign->value, 'creator_id' => $userA->id]);
    $playerA = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $userA->id, 'slot' => 1]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $userB->id, 'slot' => 2]);
    CampaignGame::factory()->create([
        'campaign_id' => $campaign->id,
        'crew_a_id' => $crewA->id,
        'crew_b_id' => $crewB->id,
        'base_game_id' => $game->id,
        'week_number' => 1,
    ]);

    $this->actingAs($userA)->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('game.players', function ($players) use ($playerA) {
            $upgradeNames = collect(collect($players)->firstWhere('slot', $playerA->slot)['references']['upgrades'] ?? [])->pluck('name');

            return $upgradeNames->contains('Borrowed Crew Card Upgrade');
        }));
});

it("does not surface an opponent's Crew Card Upgrade borrow in a player's own references", function () {
    $userA = User::factory()->create(['email_verified_at' => now()]);
    $userA->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $userB = User::factory()->create(['email_verified_at' => now()]);
    $userB->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    $campaign = Campaign::factory()->active()->create(['organizer_user_id' => $userA->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $userA->id]);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userB->id]);
    $crewA = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userA->id, 'faction' => FactionEnum::Arcanists->value]);
    $crewB = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userB->id, 'faction' => FactionEnum::Guild->value]);

    $opponentUpgrade = Upgrade::factory()->create(['name' => "Opponent's Crew Card Upgrade"]);
    CampaignCrewCardAdvancement::factory()->create([
        'campaign_crew_id' => $crewB->id,
        'crew_card_effect_id' => $opponentUpgrade->id,
        'crew_card_effect_type' => Upgrade::class,
    ]);

    $game = Game::factory()->create(['format' => GameFormatEnum::Campaign->value, 'status' => GameStatusEnum::InProgress->value, 'creator_id' => $userA->id]);
    $playerA = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $userA->id, 'slot' => 1]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $userB->id, 'slot' => 2]);
    CampaignGame::factory()->create([
        'campaign_id' => $campaign->id,
        'crew_a_id' => $crewA->id,
        'crew_b_id' => $crewB->id,
        'base_game_id' => $game->id,
        'week_number' => 1,
    ]);

    $this->actingAs($userA)->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('game.players', function ($players) use ($playerA) {
            $upgradeNames = collect(collect($players)->firstWhere('slot', $playerA->slot)['references']['upgrades'] ?? [])->pluck('name');

            return ! $upgradeNames->contains("Opponent's Crew Card Upgrade");
        }));
});
