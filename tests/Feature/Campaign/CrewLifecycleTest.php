<?php

use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\CustomCharacter;
use App\Models\Keyword;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

function lcUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

/** @return array{User, Campaign, CampaignCrew, CustomCharacter} */
function crewWithLeader(?array $optionalRules = null): array
{
    $user = lcUser();
    $campaign = Campaign::factory()->active()->create([
        'organizer_user_id' => $user->id,
        'current_week' => 4,
        'optional_rules' => $optionalRules,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'faction' => FactionEnum::Resurrectionists->value,
    ]);
    $leader = CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'L',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6, 'base' => 30,
    ]);

    return [$user, $campaign, $crew, $leader];
}

// ───── Leader annihilation + miraculous recovery ─────

it('first annihilation flips miraculous_recovery_used but keeps the leader current', function () {
    [$user, $campaign, $crew, $leader] = crewWithLeader();

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.annihilate', [$campaign, $crew->share_code]))
        ->assertRedirect();

    $leader->refresh();
    expect($leader->miraculous_recovery_used)->toBeTrue();
    expect($leader->current)->toBeTrue();
    expect($leader->annihilated_at)->toBeNull();
});

it('second annihilation retires the leader', function () {
    [$user, $campaign, $crew, $leader] = crewWithLeader();
    $leader->update(['miraculous_recovery_used' => true]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.annihilate', [$campaign, $crew->share_code]))
        ->assertRedirect();

    $leader->refresh();
    expect($leader->current)->toBeFalse();
    expect($leader->annihilated_at)->not->toBeNull();
});

it('annihilate refuses when there is no active leader', function () {
    [$user, $campaign, $crew] = crewWithLeader();
    CustomCharacter::query()->where('campaign_crew_id', $crew->id)->update(['current' => false]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.annihilate', [$campaign, $crew->share_code]))
        ->assertRedirect();
});

it('blocks non-owner from annihilating', function () {
    [, $campaign, $crew] = crewWithLeader();
    $other = lcUser();

    $this->actingAs($other)
        ->post(route('campaigns.crews.leader.annihilate', [$campaign, $crew->share_code]))
        ->assertForbidden();
});

// ───── Starting Anew ─────

it('starting-anew wipes arsenal + leader and adds 5 scrip per week elapsed', function () {
    [$user, $campaign, $crew, $leader] = crewWithLeader();
    $arsenal = CampaignArsenalModel::factory()->count(3)->create(['campaign_crew_id' => $crew->id]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-anew', [$campaign, $crew->share_code]))
        ->assertRedirect();

    // Week 4 → 3 weeks elapsed (week 1 doesn't count) → 15 scrip
    $crew->refresh();
    expect($crew->scrip)->toBe(15);
    expect($crew->starting_anew_at)->not->toBeNull();
    expect($crew->faction)->toBeNull(); // reset for fresh declare
    expect($leader->fresh()->current)->toBeFalse();

    // All live arsenal models marked removed_at.
    $remaining = CampaignArsenalModel::where('campaign_crew_id', $crew->id)
        ->whereNull('removed_at')->whereNull('annihilated_at')
        ->count();
    expect($remaining)->toBe(0);
});

it('starting-anew on week 1 grants 0 bonus scrip', function () {
    [$user, $campaign, $crew] = crewWithLeader();
    $campaign->update(['current_week' => 1]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-anew', [$campaign, $crew->share_code]))
        ->assertRedirect();

    expect($crew->fresh()->scrip)->toBe(0);
});

// ───── Cut 'Em Up For Parts ─────

it('scrap-model annihilates the model and grants ceil(cost/2) scrip', function () {
    [$user, $campaign, $crew] = crewWithLeader(['cut_em_up' => true]);
    $crew->update(['scrip' => 0]);
    $char = Character::factory()->create(['cost' => 7]);
    $arsenal = CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crew->id,
        'character_id' => $char->id,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.arsenal.scrap', [$campaign, $crew->share_code, $arsenal->id]))
        ->assertRedirect();

    expect($arsenal->fresh()->annihilated_at)->not->toBeNull();
    expect($crew->fresh()->scrip)->toBe(4); // ceil(7/2)
});

it('a double-submitted scrap only credits scrip once', function () {
    [$user, $campaign, $crew] = crewWithLeader(['cut_em_up' => true]);
    $crew->update(['scrip' => 0]);
    $char = Character::factory()->create(['cost' => 7]);
    $arsenal = CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crew->id,
        'character_id' => $char->id,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.arsenal.scrap', [$campaign, $crew->share_code, $arsenal->id]))
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('campaigns.crews.arsenal.scrap', [$campaign, $crew->share_code, $arsenal->id]))
        ->assertRedirect();

    expect($crew->fresh()->scrip)->toBe(4); // ceil(7/2), only once
});

it('scrap-model refuses when the option is disabled', function () {
    [$user, $campaign, $crew] = crewWithLeader(['cut_em_up' => false]);
    $char = Character::factory()->create(['cost' => 7]);
    $arsenal = CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crew->id,
        'character_id' => $char->id,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.arsenal.scrap', [$campaign, $crew->share_code, $arsenal->id]))
        ->assertRedirect();

    expect($arsenal->fresh()->annihilated_at)->toBeNull();
});

it('scrap-model refuses on already-annihilated models', function () {
    [$user, $campaign, $crew] = crewWithLeader(['cut_em_up' => true]);
    $char = Character::factory()->create(['cost' => 7]);
    $arsenal = CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crew->id,
        'character_id' => $char->id,
        'annihilated_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.arsenal.scrap', [$campaign, $crew->share_code, $arsenal->id]))
        ->assertRedirect();

    expect($crew->fresh()->scrip)->toBe(0);
});

// ───── Stay Dead enforcement ─────

it('Stay Dead removes annihilated unique characters from the weekly hire pool', function () {
    [$user, $campaign, $crew] = crewWithLeader(['stay_dead' => true]);
    $kw = Keyword::factory()->create();
    $crew->update(['keyword_1_id' => $kw->id, 'scrip' => 20]);

    // Unique character that's been annihilated previously.
    $unique = Characteristic::factory()->create(['name' => 'Unique']);
    $deadChar = Character::factory()->create(['cost' => 6, 'faction' => FactionEnum::Resurrectionists]);
    $deadChar->keywords()->attach($kw);
    $deadChar->characteristics()->attach($unique);
    CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crew->id,
        'character_id' => $deadChar->id,
        'annihilated_at' => now()->subWeek(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('campaigns.crews.weekly-hire.edit', [$campaign, $crew->share_code]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('hireable', function ($hireable) use ($deadChar) {
                $ids = collect($hireable)->pluck('id')->all();

                return ! in_array($deadChar->id, $ids, true);
            })
        );
});

// ───── No Injuries optional rule ─────

it('No Injuries skips Phases 5 + 6 and locks aftermath', function () {
    [$user, $campaign, $crew] = crewWithLeader(['no_injuries' => true]);

    // Spin up an aftermath in Phase 5.
    $game = \App\Models\Campaign\CampaignGame::factory()->create(['campaign_id' => $campaign->id, 'crew_a_id' => $crew->id]);
    $aftermath = \App\Models\Campaign\CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), ['attempts' => []])
        ->assertRedirect();

    $aftermath->refresh();
    expect($aftermath->status)->toBe('locked');
    // Phase advanced past 6 since both 5 and 6 were skipped.
    expect($aftermath->current_phase)->toBeGreaterThanOrEqual(6);
});
