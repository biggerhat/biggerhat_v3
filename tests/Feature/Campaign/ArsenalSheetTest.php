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

it('exposes crew_card_custom_upgrade_id for the owner once a crew card has been named/saved, and null otherwise', function () {
    $effect = CampaignCrewCard::factory()->create(['name' => 'Loot Their Stash']);
    $owner = sheetUser();
    $other = sheetUser();
    [$campaign, $crew] = crewFor2($owner);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $other->id]);
    $crew->update(['crew_card_effect_id' => $effect->id]);

    // No name ever given — no Card Creator copy exists yet.
    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('crew.crew_card_custom_upgrade_id', null));

    $this->actingAs($owner)
        ->post(route('campaigns.crews.starting-arsenal.update', [$campaign, $crew->share_code]), [
            'hires' => [],
            'crew_card_effect_id' => $effect->id,
            'crew_card_name' => 'My Crew Card',
        ])
        ->assertRedirect();

    $upgrade = \App\Models\CustomUpgrade::where('campaign_crew_id', $crew->id)->where('is_campaign_crew_card', true)->firstOrFail();

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('crew.crew_card_custom_upgrade_id', $upgrade->id));

    // A non-owner member sees the crew card but never this owner-only edit link.
    $this->actingAs($other)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('crew.crew_card_custom_upgrade_id', null));
});

it('exposes story_log entries in chronological order, including games with no story written', function () {
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
        'story_entry' => null, // no entry written — still appears, just without story text
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
            ->has('story_log', 3)
            ->where('story_log.0.story_entry', 'First game of the campaign.')
            ->where('story_log.0.week_number', 1)
            ->where('story_log.1.story_entry', null)
            ->where('story_log.1.week_number', 2)
            ->where('story_log.2.story_entry', 'Second entry, more recent.')
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

it('leader_advancements resolves a held Crew Card effect\'s name, since the catalog list excludes held effects', function () {
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
    \App\Models\Campaign\CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crew->id,
        'crew_card_effect_id' => $borrowedEffect->id,
        'crew_card_effect_type' => CampaignCrewCard::class,
    ]);
    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::CrewCard->value,
        // Real app-created rows always have both set identically for this
        // table (resolveCoreCatalogId() falls through to $catalogId, since
        // CrewCard has no AdvancementAction/AdvancementAbility row to resolve
        // a "core" id from) — set both here to match.
        'advancement_catalog_id' => $borrowedEffect->id,
        'catalog_core_id' => $borrowedEffect->id,
        'applied_to_action_index' => -1,
        'position_in_xp_track' => 0,
        'acquired_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leader_advancements.0.context_chain', ['Borrowed Boon'])
            ->has('leader_advancements.0.acquired_at')
        );
});

it('leader_advancements context_chain lists a whole-card Crew Card grant\'s actions/abilities alongside its name', function () {
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

    $borrowedEffect = CampaignCrewCard::factory()->create(['name' => 'Loot Their Stash']);
    $grantedAction = \App\Models\Action::factory()->create(['name' => 'Swing']);
    $grantedAbility = \App\Models\Ability::factory()->create(['name' => 'Aegis']);
    $borrowedEffect->actions()->attach($grantedAction->id, ['is_signature_action' => false]);
    $borrowedEffect->abilities()->attach($grantedAbility->id);

    \App\Models\Campaign\CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crew->id,
        'crew_card_effect_id' => $borrowedEffect->id,
        'crew_card_effect_type' => CampaignCrewCard::class,
    ]);
    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::CrewCard->value,
        'advancement_catalog_id' => $borrowedEffect->id,
        'catalog_core_id' => $borrowedEffect->id,
        'applied_to_action_index' => -1,
        'position_in_xp_track' => 0,
        'acquired_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leader_advancements.0.context_chain', ['Loot Their Stash', 'Swing, Aegis'])
        );
});

it('crew_card_advancements exposes only the single picked item for a new-style crew_upgrade Tier-4 pick, not the whole source card', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $upgrade = \App\Models\Upgrade::factory()->create(['domain' => \App\Enums\UpgradeDomainTypeEnum::Crew->value, 'name' => 'Source Card']);
    $pickedAction = \App\Models\Action::factory()->create(['name' => 'Picked Action', 'description' => 'Picked action text']);
    $otherAbility = \App\Models\Ability::factory()->create(['name' => 'Not Picked Ability']);
    $upgrade->actions()->attach($pickedAction->id);
    $upgrade->abilities()->attach($otherAbility->id);

    \App\Models\Campaign\CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crew->id,
        'crew_card_effect_id' => $upgrade->id,
        'crew_card_effect_type' => \App\Models\Upgrade::class,
        'crew_card_item_type' => 'action',
        'crew_card_item_id' => $pickedAction->id,
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('crew.crew_card_advancements.0.effect.name', 'Picked Action')
            ->where('crew.crew_card_advancements.0.effect.body', 'Picked action text')
            ->where('crew.crew_card_advancements.0.effect.abilities', [])
            ->has('crew.crew_card_advancements.0.effect.actions', 1)
        );
});

it('CombinedCrewCardEffects::build includes every whole-card borrow\'s own description, not just the starter\'s', function () {
    $owner = sheetUser();
    [, $crew] = crewFor2($owner);

    $starter = CampaignCrewCard::factory()->create(['description' => 'Starter default text.']);
    $crew->update(['crew_card_effect_id' => $starter->id]);

    // Two separate whole-card (campaign_crew_card source) Tier-4 borrows —
    // each one's own "Default Text" description should show up, not just
    // the first/starter's.
    $firstBorrow = CampaignCrewCard::factory()->create(['description' => 'First borrowed default text.']);
    $secondBorrow = CampaignCrewCard::factory()->create(['description' => 'Second borrowed default text.']);

    \App\Models\Campaign\CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crew->id,
        'crew_card_effect_id' => $firstBorrow->id,
        'crew_card_effect_type' => CampaignCrewCard::class,
    ]);
    \App\Models\Campaign\CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crew->id,
        'crew_card_effect_id' => $secondBorrow->id,
        'crew_card_effect_type' => CampaignCrewCard::class,
    ]);

    $crew = \App\Support\Campaign\CombinedCrewCardEffects::eagerLoad($crew->fresh());
    $items = \App\Support\Campaign\CombinedCrewCardEffects::build($crew);

    $textBodies = collect($items)->where('type', 'text')->pluck('data.body')->all();

    expect($textBodies)->toBe([
        'Starter default text.',
        'First borrowed default text.',
        'Second borrowed default text.',
    ]);
});

it('leader_advancements context_chain shows Mod > Action > Unit for an Attack Mod applied to a Totem action', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $leader = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Chain Leader',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
        'base' => 30,
    ]);
    $totem = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_totem' => true,
        'current' => true,
        'name' => 'Rat King',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 6, 'defense' => 4, 'willpower' => 4, 'speed' => 5,
        'base' => 30,
        'actions' => [['name' => 'Gnaw', 'type' => 'attack', 'stat' => 5]],
    ]);
    $mod = \App\Models\Campaign\AdvancementAttackMod::factory()->create(['name' => 'Precise Strike']);

    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::AttackMod->value,
        'advancement_catalog_id' => $mod->id,
        'catalog_core_id' => $mod->id,
        'applied_to_custom_character_id' => $totem->id,
        'applied_to_action_index' => 0,
        'position_in_xp_track' => 0,
        'acquired_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('leader_advancements.0.context_chain', ['Precise Strike', 'Gnaw', 'Rat King']));
});

it('leader_advancements context_chain resolves the Totem advancement to its own player-chosen name, not the template', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $leader = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Totem Leader',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
        'base' => 30,
    ]);
    $template = \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'is_campaign_totem_template' => true,
        'name' => 'Stock Totem Template',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 6, 'defense' => 4, 'willpower' => 4, 'speed' => 5, 'base' => 30,
    ]);
    // The player renamed their totem — the actual created instance, not the template.
    \App\Models\CustomCharacter::create([
        'user_id' => $owner->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_totem' => true,
        'current' => true,
        'name' => 'My Custom Totem',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 6, 'defense' => 4, 'willpower' => 4, 'speed' => 5, 'base' => 30,
    ]);

    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::Totem->value,
        'advancement_catalog_id' => $template->id,
        'catalog_core_id' => $template->id,
        'applied_to_action_index' => -1,
        'position_in_xp_track' => 0,
        'acquired_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('leader_advancements.0.context_chain', ['My Custom Totem']));
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

    // free_choice_source_name reads display_name, but CharacterObserver::
    // creating() unconditionally derives display_name from `name` (+ title)
    // on every create — a display_name override is silently discarded. `name`
    // is the field that actually matters, and `title` must be pinned to null
    // since the factory otherwise has a random 5% chance of appending one.
    $ally = Character::factory()->create(['name' => 'Rusty Alyce', 'title' => null]);
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

it('story_log exposes the linked completed game\'s uuid so View Game Log can route to games.summary', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $completedBaseGame = \App\Models\Game::factory()->create(['status' => \App\Enums\GameStatusEnum::Completed->value]);
    $completedCampaignGame = \App\Models\Campaign\CampaignGame::factory()->create([
        'campaign_id' => $campaign->id,
        'crew_a_id' => $crew->id,
        'base_game_id' => $completedBaseGame->id,
        'week_number' => 1,
    ]);
    \App\Models\Campaign\CampaignAftermath::factory()->create([
        'campaign_game_id' => $completedCampaignGame->id,
        'campaign_crew_id' => $crew->id,
        'status' => 'locked',
    ]);

    // No linked base game at all — game_uuid must be null, not crash.
    $noGameCampaignGame = \App\Models\Campaign\CampaignGame::factory()->create([
        'campaign_id' => $campaign->id,
        'crew_a_id' => $crew->id,
        'base_game_id' => null,
        'week_number' => 2,
    ]);
    \App\Models\Campaign\CampaignAftermath::factory()->create([
        'campaign_game_id' => $noGameCampaignGame->id,
        'campaign_crew_id' => $crew->id,
        'status' => 'locked',
    ]);

    // Linked game exists but isn't finished yet — must not link to a
    // not-yet-viewable summary page.
    $inProgressBaseGame = \App\Models\Game::factory()->create(['status' => \App\Enums\GameStatusEnum::InProgress->value]);
    $inProgressCampaignGame = \App\Models\Campaign\CampaignGame::factory()->create([
        'campaign_id' => $campaign->id,
        'crew_a_id' => $crew->id,
        'base_game_id' => $inProgressBaseGame->id,
        'week_number' => 3,
    ]);
    \App\Models\Campaign\CampaignAftermath::factory()->create([
        'campaign_game_id' => $inProgressCampaignGame->id,
        'campaign_crew_id' => $crew->id,
        'status' => 'locked',
    ]);

    $this->actingAs($owner)
        ->get(route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('story_log', function ($log) use ($completedBaseGame) {
                $byWeek = collect($log)->keyBy(fn ($e) => $e['week_number']);

                return $byWeek[1]['game_uuid'] === $completedBaseGame->uuid
                    && $byWeek[2]['game_uuid'] === null
                    && $byWeek[3]['game_uuid'] === null;
            })
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

it('addManualArsenalModel lets the crew owner add a unit outside the normal hire flow, no restrictions', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);
    $character = Character::factory()->create(['display_name' => 'Ad Hoc Ally']);

    $this->actingAs($owner)
        ->post(route('campaigns.crews.arsenal.models.store', [$campaign, $crew->share_code]), [
            'character_id' => $character->id,
            'label' => 'Summoned mid-game',
        ])
        ->assertRedirect();

    $model = CampaignArsenalModel::where('campaign_crew_id', $crew->id)->where('character_id', $character->id)->first();
    expect($model)->not->toBeNull()
        ->and($model->label)->toBe('Summoned mid-game')
        ->and($model->acquired_via)->toBe('manual')
        ->and($model->acquired_week)->toBe($campaign->current_week);
});

it('addManualArsenalModel rejects a non-owner', function () {
    $owner = sheetUser();
    $other = sheetUser();
    [$campaign, $crew] = crewFor2($owner);
    $character = Character::factory()->create();

    $this->actingAs($other)
        ->post(route('campaigns.crews.arsenal.models.store', [$campaign, $crew->share_code]), ['character_id' => $character->id])
        ->assertForbidden();

    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(0);
});

it('addManualArsenalModel rejects an unknown character_id', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);

    $this->actingAs($owner)
        ->post(route('campaigns.crews.arsenal.models.store', [$campaign, $crew->share_code]), ['character_id' => 999999])
        ->assertSessionHasErrors('character_id');
});

it('updateArsenalModel lets the crew owner rename an already-hired model', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);
    $character = Character::factory()->create();
    $model = CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crew->id,
        'character_id' => $character->id,
        'label' => 'Old Nickname',
    ]);

    $this->actingAs($owner)
        ->put(route('campaigns.crews.arsenal.models.update', [$campaign, $crew->share_code, $model]), [
            'label' => 'New Nickname',
        ])
        ->assertRedirect();

    expect($model->fresh()->label)->toBe('New Nickname');
});

it('updateArsenalModel can clear a model\'s nickname', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);
    $character = Character::factory()->create();
    $model = CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crew->id,
        'character_id' => $character->id,
        'label' => 'Old Nickname',
    ]);

    $this->actingAs($owner)
        ->put(route('campaigns.crews.arsenal.models.update', [$campaign, $crew->share_code, $model]), ['label' => null])
        ->assertRedirect();

    expect($model->fresh()->label)->toBeNull();
});

it('updateArsenalModel rejects a non-owner', function () {
    $owner = sheetUser();
    $other = sheetUser();
    [$campaign, $crew] = crewFor2($owner);
    $character = Character::factory()->create();
    $model = CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crew->id,
        'character_id' => $character->id,
        'label' => 'Old Nickname',
    ]);

    $this->actingAs($other)
        ->put(route('campaigns.crews.arsenal.models.update', [$campaign, $crew->share_code, $model]), ['label' => 'Hijacked'])
        ->assertForbidden();

    expect($model->fresh()->label)->toBe('Old Nickname');
});

it('updateArsenalModel rejects a model belonging to a different crew', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);
    [, $otherCrew] = crewFor2($owner);
    $character = Character::factory()->create();
    $model = CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $otherCrew->id,
        'character_id' => $character->id,
        'label' => 'Old Nickname',
    ]);

    $this->actingAs($owner)
        ->put(route('campaigns.crews.arsenal.models.update', [$campaign, $crew->share_code, $model]), ['label' => 'Hijacked'])
        ->assertForbidden();

    expect($model->fresh()->label)->toBe('Old Nickname');
});

it('addManualEquipment lets the crew owner add equipment outside the normal Barter/Aftermath flow', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);
    $equipment = \App\Models\Upgrade::factory()->campaignEquipment()->create(['name' => 'Found Trinket']);

    $this->actingAs($owner)
        ->post(route('campaigns.crews.arsenal.equipment.store', [$campaign, $crew->share_code]), [
            'equipment_upgrade_id' => $equipment->id,
        ])
        ->assertRedirect();

    $row = \App\Models\Campaign\CampaignEquipment::where('campaign_crew_id', $crew->id)->where('equipment_upgrade_id', $equipment->id)->first();
    expect($row)->not->toBeNull()->and($row->source)->toBe('manual');
});

it('addManualEquipment rejects a non-owner', function () {
    $owner = sheetUser();
    $other = sheetUser();
    [$campaign, $crew] = crewFor2($owner);
    $equipment = \App\Models\Upgrade::factory()->campaignEquipment()->create();

    $this->actingAs($other)
        ->post(route('campaigns.crews.arsenal.equipment.store', [$campaign, $crew->share_code]), ['equipment_upgrade_id' => $equipment->id])
        ->assertForbidden();

    expect(\App\Models\Campaign\CampaignEquipment::where('campaign_crew_id', $crew->id)->count())->toBe(0);
});

it('addManualEquipment rejects an upgrade that is not a campaign equipment catalog row', function () {
    $owner = sheetUser();
    [$campaign, $crew] = crewFor2($owner);
    $notEquipment = \App\Models\Upgrade::factory()->create(['domain' => \App\Enums\UpgradeDomainTypeEnum::Character->value]);

    $this->actingAs($owner)
        ->post(route('campaigns.crews.arsenal.equipment.store', [$campaign, $crew->share_code]), [
            'equipment_upgrade_id' => $notEquipment->id,
        ])
        ->assertSessionHasErrors('equipment_upgrade_id');
});
