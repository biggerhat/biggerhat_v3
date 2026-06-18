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
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $owner->id,
        'faction' => FactionEnum::Resurrectionists->value,
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
