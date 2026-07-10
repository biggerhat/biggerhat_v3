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

    $starter = \App\Models\Campaign\CampaignCrewCard::factory()->create(['name' => 'Fire in the Hole', 'description' => 'Starter body text.']);
    $crewA->update(['crew_card_effect_id' => $starter->id]);

    $borrowedEffect = \App\Models\Campaign\CampaignCrewCard::factory()->create(['name' => 'Borrowed Boon']);
    $master = Character::factory()->create(['station' => \App\Enums\CharacterStationEnum::Master->value]);
    \App\Models\Campaign\CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crewA->id,
        'crew_card_effect_id' => $borrowedEffect->id,
        'source_master_id' => $master->id,
    ]);

    $this->actingAs($userA)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('campaign_context.crew_a_card.effect.name', 'Fire in the Hole')
            ->where('campaign_context.crew_a_card.effect.body', 'Starter body text.')
            ->where('campaign_context.crew_a_card.borrowed.0.effect.name', 'Borrowed Boon')
            ->where('campaign_context.crew_a_card.borrowed.0.source_master_name', $master->fresh()->display_name)
            ->where('campaign_context.crew_b_card.effect', null)
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
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hired->id]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'character_ids' => [$hired->id],
        ])
        ->assertOk();

    $player = $game->players()->where('user_id', $userA->id)->first();

    expect(\App\Models\GameCrewMember::where('game_id', $game->id)->where('game_player_id', $player->id)->pluck('display_name')->all())
        ->toContain($hired->display_name)
        ->and(\App\Models\GameCrewMember::where('game_id', $game->id)->where('game_player_id', $player->id)->where('hiring_category', 'leader')->exists())
        ->toBeTrue();
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
        ->postJson(route('games.setup.campaign-crew', $game->uuid), ['character_ids' => []])
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
            'character_ids' => [$hired->id],
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
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hired->id]);

    $equipmentUpgrade = \App\Models\Upgrade::factory()->campaignEquipment()->create(['name' => 'Owned Trinket']);
    \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crewA->id, 'equipment_upgrade_id' => $equipmentUpgrade->id]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'character_ids' => [$hired->id],
            'equipment_assignments' => [
                ['equipment_id' => $equipmentUpgrade->id, 'target' => (string) $hired->id],
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

it('submitCampaignCrew rejects an equipment_assignments target outside the Leader/hired set', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    $equipmentUpgrade = \App\Models\Upgrade::factory()->campaignEquipment()->create();
    \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crewA->id, 'equipment_upgrade_id' => $equipmentUpgrade->id]);
    $notHired = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'character_ids' => [],
            'equipment_assignments' => [
                ['equipment_id' => $equipmentUpgrade->id, 'target' => (string) $notHired->id],
            ],
        ])
        ->assertStatus(422);

    expect(\App\Models\GameCrewMember::where('game_id', $game->id)->exists())->toBeFalse();
});

it('submitCampaignCrew rejects assigning more copies of an equipment than the crew owns', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    $hiredA = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);
    $hiredB = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Arcanists->value]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hiredA->id]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $hiredB->id]);

    // Only one copy owned.
    $equipmentUpgrade = \App\Models\Upgrade::factory()->campaignEquipment()->create();
    \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crewA->id, 'equipment_upgrade_id' => $equipmentUpgrade->id]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), [
            'character_ids' => [$hiredA->id, $hiredB->id],
            'equipment_assignments' => [
                ['equipment_id' => $equipmentUpgrade->id, 'target' => (string) $hiredA->id],
                ['equipment_id' => $equipmentUpgrade->id, 'target' => (string) $hiredB->id],
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
        ->postJson(route('games.setup.campaign-crew', $game->uuid), ['character_ids' => []])
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
            ]])
        );
});
