<?php

use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Character;
use App\Models\User;
use Laravel\Pennant\Feature;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

function sheetUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

function crewFor2(User $owner): array
{
    $campaign = Campaign::factory()->create(['organizer_user_id' => $owner->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $owner->id]);
    // Declare keywords so the sheet's keywordOne/keywordTwo eager-load actually
    // hits the keywords table (regression: it had selected a non-existent
    // `faction` column).
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $owner->id,
        'faction' => FactionEnum::Resurrectionists->value,
        'keyword_1_id' => \App\Models\Keyword::factory()->create()->id,
        'keyword_2_id' => \App\Models\Keyword::factory()->create()->id,
    ]);

    return [$campaign, $crew];
}

it('renders the arsenal sheet for the crew owner', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Campaigns/ArsenalSheet')
            ->where('view_mode.is_owner', true)
            ->where('view_mode.is_member', true)
        );
});

it('renders the arsenal sheet for non-owner campaign members', function () {
    $owner = sheetUser();
    $teammate = sheetUser();
    [$campaign, $crew] = crewFor2($owner);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $teammate->id]);

    $this->actingAs($teammate)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('view_mode.is_owner', false)
            ->where('view_mode.is_member', true)
        );
});

it('blocks non-members from the authed arsenal route', function () {
    $owner = sheetUser();
    $outsider = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $this->actingAs($outsider)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertForbidden();
});

it('serves the public share link without auth when the feature flag is on', function () {
    Feature::for(null)->activate('m4e-campaign-mode');
    $owner = sheetUser();
    [, $crew] = crewFor2($owner);

    // Anonymous request.
    $this->get(route('campaigns.crews.arsenal.share', $crew->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Campaigns/ArsenalSheet')
            ->where('view_mode.is_owner', false)
            ->where('view_mode.is_member', false)
        );
});

it('hides the public share link with a 404 when the campaign feature is off', function () {
    $owner = sheetUser();
    [, $crew] = crewFor2($owner);

    // Anonymous + feature flag off + no permission → 404 (campaign.access gate
    // runs before everything since the public share route has no auth).
    $this->get(route('campaigns.crews.arsenal.share', $crew->share_code))
        ->assertNotFound();
});

it('returns 404 for an unknown share code', function () {
    $perm = sheetUser();
    $this->actingAs($perm)
        ->get(route('campaigns.crews.arsenal.share', 'no-such-code'))
        ->assertNotFound();
});

it('exposes arsenal models + crew card effect in the payload', function () {
    $effect = CampaignCrewCard::factory()->create(['name' => 'Expert Coordination']);
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);
    $crew->update(['crew_card_effect_id' => $effect->id, 'scrip' => 2]);
    $char = Character::factory()->create(['cost' => 6]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id, 'character_id' => $char->id]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('crew.crew_card_effect.name', 'Expert Coordination')
            ->where('crew.scrip', 2)
            ->has('crew.arsenal_models', 1)
            ->where('campaign_rating.value', 0)
        );
});

it('exposes story_log entries in chronological order, only for locked aftermaths with a written entry', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $gameA = \App\Models\Campaign\CampaignGame::factory()->create(['campaign_id' => $campaign->id, 'crew_a_id' => $crew->id, 'week_number' => 1]);
    $gameB = \App\Models\Campaign\CampaignGame::factory()->create(['campaign_id' => $campaign->id, 'crew_a_id' => $crew->id, 'week_number' => 2]);
    $gameC = \App\Models\Campaign\CampaignGame::factory()->create(['campaign_id' => $campaign->id, 'crew_a_id' => $crew->id, 'week_number' => 3]);

    \App\Models\Campaign\CampaignAftermath::factory()->create([
        'campaign_game_id' => $gameA->id,
        'campaign_crew_id' => $crew->id,
        'status' => 'locked',
        'story_entry' => 'First game of the campaign.',
        'created_at' => now()->subDays(2),
    ]);
    \App\Models\Campaign\CampaignAftermath::factory()->create([
        'campaign_game_id' => $gameB->id,
        'campaign_crew_id' => $crew->id,
        'status' => 'locked',
        'story_entry' => null, // no entry written — must not appear
        'created_at' => now()->subDay(),
    ]);
    \App\Models\Campaign\CampaignAftermath::factory()->create([
        'campaign_game_id' => $gameC->id,
        'campaign_crew_id' => $crew->id,
        'status' => 'locked',
        'story_entry' => 'Second entry, more recent.',
        'created_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('story_log', 2)
            ->where('story_log.0.story_entry', 'First game of the campaign.')
            ->where('story_log.0.week_number', 1)
            ->where('story_log.1.story_entry', 'Second entry, more recent.')
        );
});

it('omits annihilated arsenal models from the active payload', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id, 'annihilated_at' => now()]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('crew.arsenal_models', 1));
});

it('computes Campaign Rating as equipment + advancements − injuries', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    // 3 active equipment + 1 annihilated (annihilated must not count).
    \App\Models\Campaign\CampaignEquipment::factory()->count(3)->create(['campaign_crew_id' => $crew->id]);
    \App\Models\Campaign\CampaignEquipment::factory()->create([
        'campaign_crew_id' => $crew->id,
        'annihilated_at' => now(),
    ]);
    // Equipment that "never counts towards your campaign rating" (pg 19, e.g.
    // Lucky Upstart Special) must not bump the CR equipment tally either.
    \App\Models\Campaign\CampaignEquipment::factory()->create([
        'campaign_crew_id' => $crew->id,
        'excludes_from_cr' => true,
    ]);

    // 2 active arsenal models + 1 with 2 injuries + 1 annihilated (must not count).
    $modelA = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $modelB = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id, 'annihilated_at' => now()]);
    $injury = \App\Models\Upgrade::factory()->campaignInjury()->create();
    \Illuminate\Support\Facades\DB::table('campaign_arsenal_model_injuries')->insert([
        ['campaign_arsenal_model_id' => $modelA->id, 'injury_upgrade_id' => $injury->id, 'created_at' => now(), 'updated_at' => now()],
        ['campaign_arsenal_model_id' => $modelA->id, 'injury_upgrade_id' => $injury->id, 'created_at' => now(), 'updated_at' => now()],
        ['campaign_arsenal_model_id' => $modelB->id, 'injury_upgrade_id' => $injury->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Leader with 2 advancements + totem with 1.
    $leader = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'CR Leader',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 14,
        'defense' => 5,
        'willpower' => 5,
        'speed' => 6,
        'base' => 30,
    ]);
    $totem = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_totem' => true,
        'current' => true,
        'name' => 'CR Totem',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 4,
        'defense' => 4,
        'willpower' => 4,
        'speed' => 5,
        'base' => 30,
    ]);
    foreach (range(1, 2) as $i) {
        \App\Models\Campaign\CampaignLeaderAdvancement::create([
            'custom_character_id' => $leader->id,
            'source_table' => \App\Enums\Campaign\AdvancementTableEnum::AttackMod->value,
            'applied_to_action_index' => -1,
            'position_in_xp_track' => $i,
            'acquired_at' => now(),
        ]);
    }
    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $totem->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::Ability->value,
        'applied_to_action_index' => -1,
        'position_in_xp_track' => 0,
        'acquired_at' => now(),
    ]);

    // CR = 3 (eq) + 3 (advancements) − 2 (active injuries on active model) = 4.
    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('campaign_rating.equipment_count', 3)
            ->where('campaign_rating.advancement_count', 3)
            ->where('campaign_rating.injury_count', 2)
            ->where('campaign_rating.value', 4)
        );
});

it('exposes Leader/Totem injuries in the payload and counts them toward Campaign Rating', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $leader = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Injured Leader',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6, 'base' => 30,
    ]);
    $totem = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_totem' => true,
        'current' => true,
        'name' => 'Injured Totem',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 4, 'defense' => 4, 'willpower' => 4, 'speed' => 5, 'base' => 30,
    ]);
    $injury = \App\Models\Upgrade::factory()->campaignInjury()->create(['name' => 'Concussed', 'description' => 'Take a nap.']);
    \Illuminate\Support\Facades\DB::table('campaign_arsenal_model_injuries')->insert([
        ['custom_character_id' => $leader->id, 'injury_upgrade_id' => $injury->id, 'created_at' => now(), 'updated_at' => now()],
        ['custom_character_id' => $totem->id, 'injury_upgrade_id' => $injury->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leader.injury_names.0.name', 'Concussed')
            ->where('leader.injury_names.0.description', 'Take a nap.')
            ->where('totem.injury_names.0.name', 'Concussed')
            ->where('totem.injury_names.0.description', 'Take a nap.')
            ->where('campaign_rating.injury_count', 2)
        );

    expect($crew->activeInjuryCount())->toBe(2);
});

it('exposes equipment actions and marks equipment locked once an advancement targets it', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $leader = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Eq Leader',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6, 'base' => 30,
    ]);
    $equipment = \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crew->id]);
    $action = \App\Models\Action::factory()->create(['name' => 'Granted Slash', 'type' => 'attack', 'stat' => 5, 'description' => 'Slash body text.']);
    $equipment->catalog->actions()->attach($action->id, ['is_signature_action' => false]);
    $ability = \App\Models\Ability::factory()->create(['name' => 'Granted Toughness', 'description' => 'Toughness body text.']);
    $equipment->catalog->abilities()->attach($ability->id);
    $trigger = \App\Models\Campaign\AdvancementAttackMod::factory()->create(['name' => 'Locked Trigger', 'flip_value' => 5]);
    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::AttackMod->value,
        'advancement_catalog_id' => $trigger->id,
        'from_equipment_id' => $equipment->id,
        'applied_to_action_id' => $action->id,
        'applied_to_action_index' => -1,
        'position_in_xp_track' => 0,
        'acquired_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('equipment.0.locked', true)
            ->where('equipment.0.applied_effects.0', 'Locked Trigger — Granted Slash')
            ->where('equipment.0.actions.0.name', 'Granted Slash')
            // Regression: the equipment card view was showing only the bare
            // description, not full action/ability rules text (QA report).
            ->where('equipment.0.actions.0.description', 'Slash body text.')
            ->where('equipment.0.actions.0.stat', '5')
            ->where('equipment.0.abilities.0.name', 'Granted Toughness')
            ->where('equipment.0.abilities.0.description', 'Toughness body text.')
        );
});

it('merges a Trigger-type Attack Mod advancement into the target action\'s own triggers, not just applied_effects', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $leader = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Eq Leader',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6, 'base' => 30,
    ]);
    $equipment = \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crew->id]);
    $action = \App\Models\Action::factory()->create(['name' => 'Granted Slash', 'type' => 'attack']);
    $equipment->catalog->actions()->attach($action->id, ['is_signature_action' => false]);
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

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('equipment.0.actions.0.name', 'Granted Slash')
            ->where('equipment.0.actions.0.triggers.0.name', 'Vicious Cut')
            ->where('equipment.0.actions.0.triggers.0.description', 'Push the target 3".')
        );
});

it('exposes the leader Leadership Experience track with filled boxes', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    // Leader whose XP track has 2 boxes filled (as logged-game XP would).
    $track = \App\Models\CustomCharacter::defaultXpTrack();
    $track[0]['filled'] = true;
    $track[1]['filled'] = true;
    \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'share_code' => 'ldr-sheet-1',
        'name' => 'Reva Cortinas',
        'display_name' => 'Reva Cortinas',
        'slug' => 'reva-cortinas',
        'faction' => FactionEnum::Resurrectionists->value,
        'tag' => 'bruiser',
        'health' => 12, 'defense' => 5, 'willpower' => 6, 'speed' => 5,
        'xp_track' => $track,
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'leader_xp_track',
            fn ($t) => collect($t)->where('filled', true)->count() === 2 && count($t) === 39,
        ));
});

it('eligible_masters includes homebrew Leaders sharing a crew keyword, not just official Characters', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    // This crew's own Leader (required for the sheet to compute eligible_masters at all).
    \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Sheet Leader',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
        'base' => 30,
    ]);

    $officialMaster = Character::factory()->create([
        'station' => \App\Enums\CharacterStationEnum::Master->value,
        'faction' => FactionEnum::Resurrectionists->value,
    ]);
    $officialMaster->keywords()->attach($crew->keyword_1_id);

    // A homebrew Leader belonging to some other crew, printed with the same
    // keyword as this crew — should be eligible to borrow a Crew Card from.
    $homebrewMaster = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Homebrew Master',
        'display_name' => 'Homebrew Master',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
        'base' => 30,
        'keywords' => [['id' => $crew->keyword_1_id, 'name' => 'Test Keyword']],
    ]);

    // A homebrew "Leader" that's stale (current = false) should NOT show up.
    \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'is_campaign_leader' => true,
        'current' => false,
        'name' => 'Stale Homebrew Master',
        'display_name' => 'Stale Homebrew Master',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
        'base' => 30,
        'keywords' => [['id' => $crew->keyword_1_id, 'name' => 'Test Keyword']],
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('eligible_masters', function ($masters) use ($officialMaster, $homebrewMaster) {
            $byId = collect($masters)->keyBy('id');

            return $byId->has($officialMaster->id)
                && $byId->get($officialMaster->id)['master_type'] === 'official'
                && $byId->has($homebrewMaster->id)
                && $byId->get($homebrewMaster->id)['master_type'] === 'custom'
                && ! $byId->keys()->contains(fn ($id) => \App\Models\CustomCharacter::find($id)?->name === 'Stale Homebrew Master');
        }));
});

it('leader_advancements resolves a held Crew Card effect\'s name + source master, since the catalog list excludes held effects', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $leader = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Log Leader',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
        'base' => 30,
    ]);

    $borrowedEffect = CampaignCrewCard::factory()->create(['name' => 'Borrowed Boon']);
    $master = Character::factory()->create(['station' => \App\Enums\CharacterStationEnum::Master->value]);
    \App\Models\Campaign\CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crew->id,
        'crew_card_effect_id' => $borrowedEffect->id,
        'source_master_id' => $master->id,
        'source_master_type' => Character::class,
    ]);
    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::CrewCard->value,
        'catalog_core_id' => $borrowedEffect->id,
        'applied_to_action_index' => -1,
        'position_in_xp_track' => 0,
        'acquired_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leader_advancements.0.crew_card_name', 'Borrowed Boon')
            ->where('leader_advancements.0.crew_card_master_name', $master->fresh()->display_name)
            ->has('leader_advancements.0.acquired_at')
        );
});

it('leader_advancements resolves the ally an Any-Joker action/ability was freely picked from', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $leader = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Joker Leader',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
        'base' => 30,
    ]);

    $ally = Character::factory()->create(['name' => 'Rusty Alyce']);
    $ability = \App\Models\Ability::factory()->create();
    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::Ability->value,
        'catalog_core_id' => $ability->id,
        'free_choice' => ['source_id' => $ability->id, 'source_character_id' => $ally->id],
        'applied_to_action_index' => -1,
        'position_in_xp_track' => 0,
        'acquired_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('leader_advancements.0.free_choice_source_name', 'Rusty Alyce'));
});

it('story_log entries carry an auto-computed tally of injuries/doctor/lucky-miss/TTW for that same week', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $game = \App\Models\Campaign\CampaignGame::factory()->create(['campaign_id' => $campaign->id, 'crew_a_id' => $crew->id, 'week_number' => 4]);
    $aftermath = \App\Models\Campaign\CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'status' => 'locked',
        'story_entry' => 'A rough week.',
    ]);

    $hired = Character::factory()->create(['faction' => FactionEnum::Resurrectionists->value]);
    $arsenalModel = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id, 'character_id' => $hired->id]);
    $injuryUpgrade = \App\Models\Upgrade::factory()->campaignInjury()->create();

    \App\Models\Campaign\CampaignArsenalModelInjury::create([
        'campaign_arsenal_model_id' => $arsenalModel->id,
        'injury_upgrade_id' => $injuryUpgrade->id,
        'acquired_aftermath_id' => $aftermath->id,
    ]);
    \App\Models\Campaign\CampaignArsenalModelInjury::create([
        'campaign_arsenal_model_id' => $arsenalModel->id,
        'injury_upgrade_id' => $injuryUpgrade->id,
        'acquired_aftermath_id' => $aftermath->id,
    ]);

    \Illuminate\Support\Facades\DB::table('campaign_aftermath_doctor')->insert([
        [
            'campaign_aftermath_id' => $aftermath->id,
            'target_arsenal_model_id' => $arsenalModel->id,
            'flip_value' => 13,
            'outcome' => 'lucky_miss_reflip',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'campaign_aftermath_id' => $aftermath->id,
            'target_arsenal_model_id' => $arsenalModel->id,
            'flip_value' => 3,
            'outcome' => 'removed',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $ttwEquipment = \App\Models\Upgrade::factory()->campaignEquipmentTtw()->create();
    \App\Models\Campaign\CampaignEquipment::factory()->create([
        'campaign_crew_id' => $crew->id,
        'equipment_upgrade_id' => $ttwEquipment->id,
        'source' => 'joker',
        'acquired_aftermath_id' => $aftermath->id,
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('story_log.0.tally.injuries', 2)
            ->where('story_log.0.tally.doctor_attempts', 2)
            ->where('story_log.0.tally.lucky_misses', 1)
            ->where('story_log.0.tally.ttw_pickups', 1)
        );
});

it('exposes an arsenal model\'s permanently gained Abilities from a Lucky Miss result', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $char = Character::factory()->create(['cost' => 6]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id, 'character_id' => $char->id]);
    $ability = \App\Models\Ability::factory()->create(['name' => 'Uncanny Luck', 'description' => 'Once per turn...']);
    $model->gainedAbilities()->attach($ability->id, ['source' => 'lucky_miss']);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('crew.arsenal_models.0.gained_abilities.0.name', 'Uncanny Luck')
            ->where('crew.arsenal_models.0.gained_abilities.0.description', 'Once per turn...')
        );
});
