<?php

use App\Enums\FactionEnum;
use App\Enums\GameFormatEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\CustomCharacter;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

function cintUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

function campaignGameSetup(): array
{
    $userA = cintUser();
    $userB = cintUser();
    $campaign = Campaign::factory()->active()->create([
        'organizer_user_id' => $userA->id,
        'current_week' => 2,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $userA->id]);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userB->id]);
    $crewA = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userA->id, 'faction' => FactionEnum::Arcanists->value]);
    $crewB = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userB->id, 'faction' => FactionEnum::Guild->value]);
    $game = Game::factory()->create([
        'format' => GameFormatEnum::Campaign->value,
        'status' => GameStatusEnum::CrewSelect->value,
        'creator_id' => $userA->id,
    ]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $userA->id, 'slot' => 1]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $userB->id, 'slot' => 2]);
    CampaignGame::factory()->create([
        'campaign_id' => $campaign->id,
        'crew_a_id' => $crewA->id,
        'crew_b_id' => $crewB->id,
        'base_game_id' => $game->id,
        'cr_a' => 1,
        'cr_b' => -2,
        'ss_bonus_to_lower' => 3,
        'encounter_size' => 50,
        'week_number' => 2,
    ]);

    return [$userA, $userB, $campaign, $crewA, $crewB, $game];
}

// ───── /games/{uuid} campaign banner ─────

it('Games/Show payload includes campaign_context when format=Campaign', function () {
    [$userA, , $campaign, $crewA] = campaignGameSetup();
    $game = Game::first();

    $this->actingAs($userA)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Games/Show')
            ->where('campaign_context.campaign.name', $campaign->name)
            ->where('campaign_context.crew_a.id', $crewA->id)
            ->where('campaign_context.cr_a', 1)
            ->where('campaign_context.cr_b', -2)
            ->where('campaign_context.ss_bonus_to_lower', 3)
        );
});

it('Games/Show campaign_context exposes each crew\'s starter + borrowed Crew Card effects', function () {
    [$userA, , $campaign, $crewA] = campaignGameSetup();
    $game = Game::first();

    $starter = \App\Models\Campaign\CampaignCrewCard::factory()->create([
        'name' => 'Fire in the Hole',
        'description' => 'Starter body text.',
        'front_image' => 'campaign-crew-cards/1/front.png',
    ]);
    $crewA->update(['crew_card_effect_id' => $starter->id]);

    $borrowedEffect = \App\Models\Campaign\CampaignCrewCard::factory()->create(['name' => 'Borrowed Boon']);
    \App\Models\Campaign\CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crewA->id,
        'crew_card_effect_id' => $borrowedEffect->id,
        'crew_card_effect_type' => \App\Models\Campaign\CampaignCrewCard::class,
    ]);

    $this->actingAs($userA)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('campaign_context.crew_a_card.effect.name', 'Fire in the Hole')
            ->where('campaign_context.crew_a_card.effect.body', 'Starter body text.')
            ->where('campaign_context.crew_a_card.effect.front_image', 'campaign-crew-cards/1/front.png')
            ->where('campaign_context.crew_a_card.borrowed.0.effect.name', 'Borrowed Boon')
            ->where('campaign_context.crew_b_card.effect', null)
        );
});

it('Games/Show campaign_context falls back to the player\'s own crew card for a solo game with no CampaignGame link', function () {
    // Regression check for a reported QA bug: "new crew, new game — not
    // getting a crew card." Root cause: buildCampaignContext() only ever
    // resolved crews via a CampaignGame row linked to the base Game, but a
    // solo game started outside the campaign hub never gets one — the whole
    // campaign_context (crew card included) silently came back null.
    $user = cintUser();
    $campaign = Campaign::factory()->active()->create(['organizer_user_id' => $user->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'faction' => FactionEnum::Arcanists->value]);
    $starter = \App\Models\Campaign\CampaignCrewCard::factory()->create(['name' => 'Solo Starter Effect']);
    $crew->update(['crew_card_effect_id' => $starter->id]);

    $game = Game::factory()->create([
        'format' => GameFormatEnum::Campaign->value,
        'status' => GameStatusEnum::CrewSelect->value,
        'creator_id' => $user->id,
        'is_solo' => true,
    ]);
    GamePlayer::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'slot' => 1,
        'faction' => FactionEnum::Arcanists->value,
    ]);
    // No CampaignGame::create() at all — this is the exact gap being fixed.
    expect(CampaignGame::where('base_game_id', $game->id)->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('campaign_context.campaign.id', $campaign->id)
            ->where('campaign_context.crew_a.id', $crew->id)
            ->where('campaign_context.crew_a_card.effect.name', 'Solo Starter Effect')
            ->where('campaign_context.crew_b', null)
        );
});

it('Games/Show campaign_context handles a linked solo game (crew_b_id null) without crashing', function () {
    // Regression check: buildCampaignContext()'s "CampaignGame row exists"
    // branch had only ever seen duel games (crewA + crewB both real crews)
    // before CampaignGameController::playLive() started eagerly linking
    // solo games too — crew_b_id is null there, and the code unconditionally
    // called applyCrewCardSignatureFlags($wrap->crewB) and read
    // $wrap->crewB->only(...), both of which throw a TypeError on null.
    $user = cintUser();
    $campaign = Campaign::factory()->active()->create(['organizer_user_id' => $user->id, 'is_solo' => true]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'faction' => FactionEnum::Arcanists->value]);
    $starter = \App\Models\Campaign\CampaignCrewCard::factory()->create(['name' => 'Linked Solo Starter']);
    $crew->update(['crew_card_effect_id' => $starter->id]);

    $game = Game::factory()->create([
        'format' => GameFormatEnum::Campaign->value,
        'status' => GameStatusEnum::MasterSelect->value,
        'creator_id' => $user->id,
        'is_solo' => true,
    ]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1, 'faction' => FactionEnum::Arcanists->value]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => null, 'slot' => 2, 'faction' => FactionEnum::Arcanists->value]);
    CampaignGame::factory()->create([
        'campaign_id' => $campaign->id,
        'crew_a_id' => $crew->id,
        'crew_b_id' => null,
        'base_game_id' => $game->id,
        'cr_b' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('campaign_context.crew_a.id', $crew->id)
            ->where('campaign_context.crew_a_card.effect.name', 'Linked Solo Starter')
            ->where('campaign_context.crew_b', null)
            ->where('campaign_context.crew_b_card.effect', null)
            ->where('campaign_context.crew_b_card.borrowed', [])
        );
});

it('Games/Show resolves the correct crew for a solo live game when the user has two crews sharing a faction', function () {
    // The actual reported bug: a user with more than one CampaignCrew
    // sharing a faction got the WRONG one resolved by the old
    // user_id+faction guessing fallback. CampaignGameController::playLive()
    // fixes this by linking the CampaignGame row eagerly at game-creation
    // time, so buildCampaignContext() never needs to guess.
    $user = cintUser();

    // An OLDER, incomplete crew in a different campaign, same faction, no
    // starter effect set — this is exactly what the old fallback could
    // wrongly resolve to (an incomplete sibling crew).
    $staleCampaign = Campaign::factory()->active()->create(['organizer_user_id' => $user->id, 'is_solo' => true]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $staleCampaign->id, 'user_id' => $user->id]);
    CampaignCrew::factory()->create([
        'campaign_id' => $staleCampaign->id,
        'user_id' => $user->id,
        'faction' => FactionEnum::Arcanists->value,
        'crew_card_effect_id' => null,
    ]);

    // The REAL, fully set-up crew for the game actually being played.
    $campaign = Campaign::factory()->active()->create(['organizer_user_id' => $user->id, 'is_solo' => true, 'current_week' => 1]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $realCrew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'faction' => FactionEnum::Arcanists->value]);
    $starter = \App\Models\Campaign\CampaignCrewCard::factory()->create(['name' => 'Specialized Tools']);
    $realCrew->update(['crew_card_effect_id' => $starter->id]);

    $this->actingAs($user)->post(route('campaigns.games.play', $campaign))->assertRedirect();
    $game = Game::query()->where('creator_id', $user->id)->latest('id')->firstOrFail();

    $this->actingAs($user)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('campaign_context.crew_a.id', $realCrew->id)
            ->where('campaign_context.crew_a_card.effect.name', 'Specialized Tools')
        );
});

it('Games/Show payload campaign_context is null for non-campaign games', function () {
    $user = cintUser();
    $game = Game::factory()->create([
        'format' => GameFormatEnum::Standard->value,
        'status' => GameStatusEnum::InProgress->value,
        'creator_id' => $user->id,
    ]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);

    $this->actingAs($user)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('campaign_context', null));
});

// ───── Arsenal-constraint enforcement at crew select ─────

it('submitCrew rejects characters not in the player crew arsenal', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    // Stock the arsenal with the allowed character + the master so the only
    // non-arsenal pick is the forbidden one. Otherwise faker's random master
    // name leaks into the error string and breaks the exact-match assertion.
    $allowed = Character::factory()->create(['cost' => 6]);
    $master = Character::factory()->create();
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $allowed->id]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $master->id]);

    // Build a crew including an outside character. title: null pins
    // display_name — CharacterObserver::creating() always recomputes it from
    // name+title, so a random Faker title here (~5% of factory calls) would
    // otherwise make the exact-match assertion below flaky.
    $forbidden = Character::factory()->create(['cost' => 7, 'name' => 'Disallowed Model', 'title' => null]);
    $build = CrewBuild::create([
        'user_id' => $userA->id,
        'master_id' => $master->id,
        'name' => 'Test',
        'faction' => FactionEnum::Arcanists->value,
        'crew_data' => [$allowed->id, $forbidden->id],
    ]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.crew', $game->uuid), [
            'crew_build_id' => $build->id,
            'slot' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['error' => 'Campaign games must hire from your arsenal — these are not in it: Disallowed Model']);
});

it('submitCrew accepts a crew composed entirely of arsenal characters', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    $char = Character::factory()->create(['cost' => 6]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $char->id]);

    $master = Character::factory()->create();
    $build = CrewBuild::create([
        'user_id' => $userA->id,
        'master_id' => $master->id,
        'name' => 'Legit Crew',
        'faction' => FactionEnum::Arcanists->value,
        'crew_data' => [$char->id],
    ]);

    // Set up master select state expected by submitCrew.
    $this->actingAs($userA)
        ->postJson(route('games.setup.crew', $game->uuid), [
            'crew_build_id' => $build->id,
            'slot' => 1,
        ])
        ->assertOk();
});

it('submitCrew arsenal check is a no-op for non-campaign games', function () {
    $user = cintUser();
    $game = Game::factory()->create([
        'format' => GameFormatEnum::Standard->value,
        'status' => GameStatusEnum::CrewSelect->value,
        'creator_id' => $user->id,
    ]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);

    $char = Character::factory()->create();
    $master = Character::factory()->create();
    $build = CrewBuild::create([
        'user_id' => $user->id,
        'master_id' => $master->id,
        'name' => 'Standard Crew',
        'faction' => FactionEnum::Guild->value,
        'crew_data' => [$char->id],
    ]);

    $this->actingAs($user)
        ->postJson(route('games.setup.crew', $game->uuid), [
            'crew_build_id' => $build->id,
            'slot' => 1,
        ])
        ->assertOk();
});

it('Master Select for a campaign game offers the player campaign leader', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup(); // format=Campaign, CrewSelect (a master-prop status)

    CustomCharacter::create([
        'user_id' => $userA->id,
        'campaign_crew_id' => $crewA->id,
        'is_campaign_leader' => true,
        'current' => true,
        'share_code' => 'ldr-test-001',
        'name' => 'Mortimer Vance',
        'display_name' => 'Mortimer Vance',
        'slug' => 'mortimer-vance',
        'faction' => FactionEnum::Arcanists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
    ]);

    $this->actingAs($userA)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('masters', [])
            ->where('campaign_leader_option.name', 'Mortimer Vance')
        );
});

it('submitCampaignCrew copies the selected arsenal models into game_crew_members, not just leader/totem', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    CustomCharacter::create([
        'user_id' => $userA->id,
        'campaign_crew_id' => $crewA->id,
        'is_campaign_leader' => true,
        'current' => true,
        'share_code' => 'ldr-test-002',
        'name' => 'Mortimer Vance',
        'display_name' => 'Mortimer Vance',
        'slug' => 'mortimer-vance-2',
        'faction' => FactionEnum::Arcanists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
    ]);

    $hired = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);
    $arsenalModel = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hired->id]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [$arsenalModel->id],
        ])
        ->assertOk();

    $player = $game->players()->where('user_id', $userA->id)->first();

    expect(\App\Models\GameCrewMember::where('game_id', $game->id)->where('game_player_id', $player->id)->pluck('display_name')->all())
        ->toContain($hired->display_name)
        ->and(\App\Models\GameCrewMember::where('game_id', $game->id)->where('game_player_id', $player->id)->where('hiring_category', 'leader')->exists())
        ->toBeTrue();
});

it('submitCampaignCrew hires exactly the selected arsenal row when the crew owns several copies of the same model', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    CustomCharacter::create([
        'user_id' => $userA->id,
        'campaign_crew_id' => $crewA->id,
        'is_campaign_leader' => true,
        'current' => true,
        'share_code' => 'ldr-test-006',
        'name' => 'Mortimer Vance',
        'display_name' => 'Mortimer Vance',
        'slug' => 'mortimer-vance-6',
        'faction' => FactionEnum::Arcanists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
    ]);

    // Three separate owned copies of the same catalog model — each its own
    // CampaignArsenalModel row (no count column; that's how ownership of
    // multiples is represented). Only one gets injured.
    $hired = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);
    $copy1 = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hired->id]);
    $copy2 = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hired->id]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hired->id]);

    $injury = \App\Models\Upgrade::factory()->campaignInjury()->create(['name' => 'Broken Arm']);
    \App\Models\Campaign\CampaignArsenalModelInjury::create([
        'campaign_arsenal_model_id' => $copy2->id,
        'injury_upgrade_id' => $injury->id,
    ]);

    // Hire only copy1 — not "every copy of Guild Guard the crew owns".
    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [$copy1->id],
        ])
        ->assertOk();

    $player = $game->players()->where('user_id', $userA->id)->first();
    $hiredMembers = \App\Models\GameCrewMember::where('game_id', $game->id)
        ->where('game_player_id', $player->id)
        ->where('display_name', $hired->display_name)
        ->get();

    // Exactly one hire, not three.
    expect($hiredMembers)->toHaveCount(1);
    // And it's copy1's injuries that carried over, not copy2's or copy3's.
    expect(collect($hiredMembers->first()->attached_upgrades)->pluck('name')->all())->toBe([]);
});

it('Games/Show exposes the Campaign Leader\'s actions/abilities via custom_character, since it has no card art', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    CustomCharacter::create([
        'user_id' => $userA->id,
        'campaign_crew_id' => $crewA->id,
        'is_campaign_leader' => true,
        'current' => true,
        'share_code' => 'ldr-test-004',
        'name' => 'Card-less Leader',
        'display_name' => 'Card-less Leader',
        'slug' => 'card-less-leader',
        'faction' => FactionEnum::Arcanists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
        'actions' => [['name' => 'Immolate', 'type' => 'attack']],
        'abilities' => [['name' => 'Cast to Cinders']],
    ]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), ['arsenal_model_ids' => []])
        ->assertOk();

    $game->update(['status' => GameStatusEnum::InProgress->value]);

    $this->actingAs($userA)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where(
                'game.players.0.crew_members',
                fn ($members) => collect($members)->firstWhere('hiring_category', 'leader')['custom_character']['actions'][0]['name'] === 'Immolate'
                    && collect($members)->firstWhere('hiring_category', 'leader')['custom_character']['abilities'][0]['name'] === 'Cast to Cinders',
            ));
});

it('submitCampaignCrew carries injuries from a previous aftermath onto the leader and hired models', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    $leader = CustomCharacter::create([
        'user_id' => $userA->id,
        'campaign_crew_id' => $crewA->id,
        'is_campaign_leader' => true,
        'current' => true,
        'share_code' => 'ldr-test-003',
        'name' => 'Injured Leader',
        'display_name' => 'Injured Leader',
        'slug' => 'injured-leader',
        'faction' => FactionEnum::Arcanists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
    ]);
    $leaderInjury = \App\Models\Upgrade::factory()->campaignInjury()->create(['name' => 'Leadfooted']);
    \App\Models\Campaign\CampaignArsenalModelInjury::create([
        'custom_character_id' => $leader->id,
        'injury_upgrade_id' => $leaderInjury->id,
    ]);

    $hired = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);
    $arsenalModel = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hired->id]);
    $modelInjury = \App\Models\Upgrade::factory()->campaignInjury()->create(['name' => 'Severe Amputation']);
    \App\Models\Campaign\CampaignArsenalModelInjury::create([
        'campaign_arsenal_model_id' => $arsenalModel->id,
        'injury_upgrade_id' => $modelInjury->id,
    ]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [$arsenalModel->id],
        ])
        ->assertOk();

    $player = $game->players()->where('user_id', $userA->id)->first();
    $members = \App\Models\GameCrewMember::where('game_id', $game->id)->where('game_player_id', $player->id)->get();

    $leaderMember = $members->firstWhere('hiring_category', 'leader');
    $hiredMember = $members->firstWhere('display_name', $hired->display_name);

    expect(collect($leaderMember->attached_upgrades)->pluck('name')->all())->toBe(['Leadfooted'])
        ->and(collect($hiredMember->attached_upgrades)->pluck('name')->all())->toBe(['Severe Amputation']);
});

it('submitCampaignCrew assigns owned equipment to the Leader or a specific hire (pg 19)', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    CustomCharacter::create([
        'user_id' => $userA->id,
        'campaign_crew_id' => $crewA->id,
        'is_campaign_leader' => true,
        'current' => true,
        'share_code' => 'ldr-test-005',
        'name' => 'Equipped Leader',
        'display_name' => 'Equipped Leader',
        'slug' => 'equipped-leader',
        'faction' => FactionEnum::Arcanists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
    ]);

    $hired = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);
    $arsenalModel = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hired->id]);

    $equipmentUpgrade = \App\Models\Upgrade::factory()->campaignEquipment()->create(['name' => 'Owned Trinket']);
    \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crewA->id, 'equipment_upgrade_id' => $equipmentUpgrade->id]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [$arsenalModel->id],
            'equipment_assignments' => [
                ['equipment_id' => $equipmentUpgrade->id, 'target' => (string) $arsenalModel->id],
            ],
        ])
        ->assertOk();

    $player = $game->players()->where('user_id', $userA->id)->first();
    $members = \App\Models\GameCrewMember::where('game_id', $game->id)->where('game_player_id', $player->id)->get();
    $hiredMember = $members->firstWhere('display_name', $hired->display_name);
    $leaderMember = $members->firstWhere('hiring_category', 'leader');

    expect(collect($hiredMember->attached_upgrades)->pluck('name')->all())->toBe(['Owned Trinket']);
    expect($leaderMember->attached_upgrades)->toBeEmpty();
});

it('submitCampaignCrew copies base + Campaign-gained characteristics onto the leader and hired models', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    $leader = CustomCharacter::create([
        'user_id' => $userA->id,
        'campaign_crew_id' => $crewA->id,
        'is_campaign_leader' => true,
        'current' => true,
        'share_code' => 'ldr-char-001',
        'name' => 'Characterful Leader',
        'display_name' => 'Characterful Leader',
        'slug' => 'characterful-leader',
        'faction' => FactionEnum::Arcanists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
        'characteristics' => ['Living', 'Unique'],
    ]);

    $living = \App\Models\Characteristic::factory()->create(['name' => 'Living']);
    $hired = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);
    $hired->characteristics()->attach($living);
    $arsenalModel = CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crewA->id,
        'character_id' => $hired->id,
        'gained_characteristics' => ['Undead'],
    ]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [$arsenalModel->id],
        ])
        ->assertOk();

    $player = $game->players()->where('user_id', $userA->id)->first();
    $members = \App\Models\GameCrewMember::where('game_id', $game->id)->where('game_player_id', $player->id)->get();
    $hiredMember = $members->firstWhere('display_name', $hired->display_name);
    $leaderMember = $members->firstWhere('hiring_category', 'leader');

    expect($leaderMember->characteristics)->toBe(['Living', 'Unique'])
        ->and($hiredMember->characteristics)->toBe(['Living', 'Undead']);
});

it('submitCampaignCrew carries equipment actions/abilities into attached_upgrades, not just description', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    $hired = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);
    $arsenalModel = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hired->id]);

    $equipmentUpgrade = \App\Models\Upgrade::factory()->campaignEquipment()->create(['name' => 'Rules-Text Trinket']);
    $action = \App\Models\Action::factory()->create(['name' => 'Zap']);
    $ability = \App\Models\Ability::factory()->create(['name' => 'Static Charge']);
    $equipmentUpgrade->actions()->attach($action->id, ['is_signature_action' => false]);
    $equipmentUpgrade->abilities()->attach($ability->id);
    \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crewA->id, 'equipment_upgrade_id' => $equipmentUpgrade->id]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [$arsenalModel->id],
            'equipment_assignments' => [
                ['equipment_id' => $equipmentUpgrade->id, 'target' => (string) $arsenalModel->id],
            ],
        ])
        ->assertOk();

    $player = $game->players()->where('user_id', $userA->id)->first();
    $hiredMember = \App\Models\GameCrewMember::where('game_id', $game->id)
        ->where('game_player_id', $player->id)
        ->firstWhere('display_name', $hired->display_name);

    $trinket = collect($hiredMember->attached_upgrades)->firstWhere('name', 'Rules-Text Trinket');
    expect(collect($trinket['actions'])->pluck('name')->all())->toBe(['Zap']);
    expect(collect($trinket['abilities'])->pluck('name')->all())->toBe(['Static Charge']);
});

it('submitCampaignCrew merges a Trigger-type Attack Mod advancement into the equipment action\'s own triggers, not just the base catalog', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    $leader = CustomCharacter::create([
        'user_id' => $userA->id,
        'campaign_crew_id' => $crewA->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Eq Leader',
        'faction' => FactionEnum::Arcanists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6, 'base' => 30,
    ]);

    $hired = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);
    $arsenalModel = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hired->id]);

    $equipmentUpgrade = \App\Models\Upgrade::factory()->campaignEquipment()->create(['name' => 'Advanced Trinket']);
    $action = \App\Models\Action::factory()->create(['name' => 'Granted Slash', 'type' => 'attack']);
    $equipmentUpgrade->actions()->attach($action->id, ['is_signature_action' => false]);
    $equipment = \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crewA->id, 'equipment_upgrade_id' => $equipmentUpgrade->id]);

    $realTrigger = \App\Models\Trigger::factory()->create(['name' => 'Vicious Cut', 'description' => 'Push the target 3".']);
    $advancementRow = \App\Models\Campaign\AdvancementAttackMod::factory()->create(['trigger_id' => $realTrigger->id, 'flip_value' => 5]);
    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::AttackMod->value,
        'advancement_catalog_id' => $advancementRow->id,
        'from_equipment_id' => $equipment->id,
        'applied_to_action_id' => $action->id,
        'applied_to_action_index' => -1,
        'position_in_xp_track' => 0,
        'acquired_at' => now(),
    ]);

    // Assigned to the Leader, not the hired model — this equipment now has
    // an advancement tied to it (pg 31), so it can only go to Leader/Totem.
    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [$arsenalModel->id],
            'equipment_assignments' => [
                ['equipment_id' => $equipmentUpgrade->id, 'target' => 'leader'],
            ],
        ])
        ->assertOk();

    $player = $game->players()->where('user_id', $userA->id)->first();
    $leaderMember = \App\Models\GameCrewMember::where('game_id', $game->id)
        ->where('game_player_id', $player->id)
        ->where('hiring_category', 'leader')
        ->first();

    $trinket = collect($leaderMember->attached_upgrades)->firstWhere('name', 'Advanced Trinket');
    $grantedAction = collect($trinket['actions'])->firstWhere('name', 'Granted Slash');
    expect(collect($grantedAction['triggers'])->pluck('name')->all())->toContain('Vicious Cut');
    expect(collect($grantedAction['triggers'])->firstWhere('name', 'Vicious Cut')['description'])->toBe('Push the target 3".');
});

it('submitCampaignCrew rejects an equipment_assignments target outside the Leader/hired set', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    $equipmentUpgrade = \App\Models\Upgrade::factory()->campaignEquipment()->create();
    \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crewA->id, 'equipment_upgrade_id' => $equipmentUpgrade->id]);
    $notHired = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [],
            'equipment_assignments' => [
                ['equipment_id' => $equipmentUpgrade->id, 'target' => (string) $notHired->id],
            ],
        ])
        ->assertStatus(422);

    expect(\App\Models\GameCrewMember::where('game_id', $game->id)->exists())->toBeFalse();
});

it('submitCampaignCrew rejects assigning advancement-tied equipment to a hired model, but allows the Leader (pg 31)', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    $leader = CustomCharacter::create([
        'user_id' => $userA->id,
        'campaign_crew_id' => $crewA->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Advanced Eq Leader',
        'faction' => FactionEnum::Arcanists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6, 'base' => 30,
    ]);

    $hired = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);
    $arsenalModel = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hired->id]);

    $equipmentUpgrade = \App\Models\Upgrade::factory()->campaignEquipment()->create(['name' => 'Locked Trinket']);
    $action = \App\Models\Action::factory()->create(['name' => 'Granted Slash', 'type' => 'attack']);
    $equipmentUpgrade->actions()->attach($action->id, ['is_signature_action' => false]);
    $equipment = \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crewA->id, 'equipment_upgrade_id' => $equipmentUpgrade->id]);

    $advancementRow = \App\Models\Campaign\AdvancementAttackMod::factory()->create(['flip_value' => 5]);
    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::AttackMod->value,
        'advancement_catalog_id' => $advancementRow->id,
        'from_equipment_id' => $equipment->id,
        'applied_to_action_id' => $action->id,
        'applied_to_action_index' => -1,
        'position_in_xp_track' => 0,
        'acquired_at' => now(),
    ]);

    // Rejected: assigning to the hired model instead of the Leader.
    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [$arsenalModel->id],
            'equipment_assignments' => [
                ['equipment_id' => $equipmentUpgrade->id, 'target' => (string) $arsenalModel->id],
            ],
        ])
        ->assertStatus(422);
    expect(\App\Models\GameCrewMember::where('game_id', $game->id)->exists())->toBeFalse();

    // Allowed: assigning to the Leader.
    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [$arsenalModel->id],
            'equipment_assignments' => [
                ['equipment_id' => $equipmentUpgrade->id, 'target' => 'leader'],
            ],
        ])
        ->assertOk();
});

it('submitCampaignCrew assigns owned equipment to a Tier-3 Totem', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    CustomCharacter::create([
        'user_id' => $userA->id,
        'campaign_crew_id' => $crewA->id,
        'is_campaign_totem' => true,
        'current' => true,
        'share_code' => 'totem-test-001',
        'name' => 'Test Totem',
        'display_name' => 'Test Totem',
        'slug' => 'test-totem',
        'faction' => FactionEnum::Arcanists->value,
        'health' => 6, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
    ]);

    $equipmentUpgrade = \App\Models\Upgrade::factory()->campaignEquipment()->create(['name' => 'Totem Trinket']);
    \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crewA->id, 'equipment_upgrade_id' => $equipmentUpgrade->id]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [],
            'equipment_assignments' => [
                ['equipment_id' => $equipmentUpgrade->id, 'target' => 'totem'],
            ],
        ])
        ->assertOk();

    $player = $game->players()->where('user_id', $userA->id)->first();
    $totemMember = \App\Models\GameCrewMember::where('game_id', $game->id)
        ->where('game_player_id', $player->id)
        ->where('hiring_category', 'totem')
        ->first();

    expect($totemMember)->not->toBeNull();
    expect(collect($totemMember->attached_upgrades)->pluck('name')->all())->toBe(['Totem Trinket']);
});

it('submitCampaignCrew rejects a totem equipment assignment when the crew has no Totem yet', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    $equipmentUpgrade = \App\Models\Upgrade::factory()->campaignEquipment()->create();
    \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crewA->id, 'equipment_upgrade_id' => $equipmentUpgrade->id]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [],
            'equipment_assignments' => [
                ['equipment_id' => $equipmentUpgrade->id, 'target' => 'totem'],
            ],
        ])
        ->assertStatus(422);

    expect(\App\Models\GameCrewMember::where('game_id', $game->id)->exists())->toBeFalse();
});

it('submitCampaignCrew rejects assigning more copies of an equipment than the crew owns', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    $hiredA = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);
    $hiredB = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);
    $arsenalModelA = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hiredA->id]);
    $arsenalModelB = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hiredB->id]);

    // Only one copy owned.
    $equipmentUpgrade = \App\Models\Upgrade::factory()->campaignEquipment()->create();
    \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crewA->id, 'equipment_upgrade_id' => $equipmentUpgrade->id]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'arsenal_model_ids' => [$arsenalModelA->id, $arsenalModelB->id],
            'equipment_assignments' => [
                ['equipment_id' => $equipmentUpgrade->id, 'target' => (string) $arsenalModelA->id],
                ['equipment_id' => $equipmentUpgrade->id, 'target' => (string) $arsenalModelB->id],
            ],
        ])
        ->assertStatus(422);

    expect(\App\Models\GameCrewMember::where('game_id', $game->id)->exists())->toBeFalse();
});

it('solo Campaign setup auto-fills a generic opponent — no opponent faction/master picker required', function () {
    $user = cintUser();
    $campaign = \App\Models\Campaign\Campaign::factory()->active()->create(['organizer_user_id' => $user->id]);
    \App\Models\Campaign\CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'faction' => FactionEnum::Arcanists->value]);
    CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'share_code' => 'ldr-solo-001',
        'name' => 'Solo Leader',
        'display_name' => 'Solo Leader',
        'slug' => 'solo-leader',
        'faction' => FactionEnum::Arcanists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
    ]);

    $this->actingAs($user)
        ->postJson(route('games.store'), [
            'season' => \App\Enums\PoolSeasonEnum::cases()[0]->value,
            'encounter_size' => 50,
            'format' => GameFormatEnum::Campaign->value,
            'is_solo' => true,
        ])
        ->assertRedirect();

    $game = Game::where('format', GameFormatEnum::Campaign->value)->where('creator_id', $user->id)->firstOrFail();
    expect($game->status)->toBe(GameStatusEnum::FactionSelect);

    // Player 1 submits faction alone — no opponent faction submission needed.
    $this->actingAs($user)
        ->postJson(route('games.setup.faction', $game->uuid), ['faction' => FactionEnum::Arcanists->value, 'slot' => 1])
        ->assertOk()
        ->assertJson(['both_done' => true]);

    $game->refresh();
    expect($game->status)->toBe(GameStatusEnum::MasterSelect);
    $opponent = $game->players()->where('slot', 2)->first();
    expect($opponent->faction)->not->toBeNull();

    // Player 1 submits master alone — opponent auto-fills to a generic placeholder.
    $this->actingAs($user)
        ->postJson(route('games.setup.master', $game->uuid), ['master_name' => 'Solo Leader', 'slot' => 1])
        ->assertOk()
        ->assertJson(['both_done' => true]);

    $game->refresh();
    expect($game->status)->toBe(GameStatusEnum::CrewSelect);
    $opponent->refresh();
    expect($opponent->master_name)->toBe('Opponent Campaign Leader');

    // Player 1 confirms their campaign crew alone — opponent auto-skips too,
    // advancing straight to Scheme Select with no further interaction needed.
    $this->actingAs($user)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), ['arsenal_model_ids' => []])
        ->assertOk()
        ->assertJson(['both_done' => true]);

    $game->refresh();
    expect($game->status)->toBe(GameStatusEnum::SchemeSelect);
    $opponent->refresh();
    expect($opponent->crew_skipped)->toBeTrue();
});

it('character_upgrades at InProgress offers the campaign crew\'s own earned equipment, not the full catalog', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();
    $game->update(['status' => GameStatusEnum::InProgress->value]);

    $owned = \App\Models\Upgrade::factory()->campaignEquipment()->create(['name' => 'Owned Trinket']);
    \App\Models\Campaign\CampaignEquipment::factory()->count(2)->create([
        'campaign_crew_id' => $crewA->id,
        'equipment_upgrade_id' => $owned->id,
    ]);
    // Unowned equipment from the catalog must not leak into the player's picker.
    \App\Models\Upgrade::factory()->campaignEquipment()->create(['name' => 'Unowned Trinket']);
    // Annihilated (inactive) copies must not count.
    \App\Models\Campaign\CampaignEquipment::factory()->create([
        'campaign_crew_id' => $crewA->id,
        'equipment_upgrade_id' => $owned->id,
        'annihilated_at' => now(),
    ]);

    $this->actingAs($userA)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('character_upgrades', [[
                'id' => $owned->id,
                'name' => 'Owned Trinket',
                'slug' => $owned->slug,
                'front_image' => $owned->front_image,
                'back_image' => $owned->back_image,
                'type' => $owned->type?->value,
                'plentiful' => 2,
                'power_bar_count' => $owned->power_bar_count,
                'description' => $owned->description,
                'is_advanced' => false,
                'actions' => [],
                'abilities' => [],
            ]])
        );
});

it('character_upgrades at InProgress carries the equipment\'s granted actions/abilities in full, not just the description', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();
    $game->update(['status' => GameStatusEnum::InProgress->value]);

    $owned = \App\Models\Upgrade::factory()->campaignEquipment()->create(['name' => 'Owned Trinket']);
    \App\Models\Campaign\CampaignEquipment::factory()->create([
        'campaign_crew_id' => $crewA->id,
        'equipment_upgrade_id' => $owned->id,
    ]);
    $action = \App\Models\Action::factory()->create(['name' => 'Granted Slash', 'type' => 'attack', 'stat' => 5]);
    $owned->actions()->attach($action->id, ['is_signature_action' => true]);
    $ability = \App\Models\Ability::factory()->create(['name' => 'Granted Ward']);
    $owned->abilities()->attach($ability->id);

    $this->actingAs($userA)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('character_upgrades.0.actions.0.name', 'Granted Slash')
            ->where('character_upgrades.0.actions.0.is_signature', true)
            ->where('character_upgrades.0.abilities.0.name', 'Granted Ward')
        );
});
