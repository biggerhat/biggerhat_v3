<?php

use App\Enums\PermissionEnum;
use App\Models\Campaign\BackAlleyDoctorResult;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignEquipment;
use App\Models\Campaign\CampaignGame;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Campaign\Equipment;
use App\Models\Campaign\Injury;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

function amUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

/** @return array{User, Campaign, CampaignCrew, CampaignGame} */
function aftermathFixture(): array
{
    $user = amUser();
    $campaign = Campaign::factory()->active()->create(['organizer_user_id' => $user->id, 'current_week' => 1]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'scrip' => 0]);
    $opponent = CampaignCrew::factory()->create(['campaign_id' => $campaign->id]);
    $game = CampaignGame::factory()->create([
        'campaign_id' => $campaign->id,
        'crew_a_id' => $crew->id,
        'crew_b_id' => $opponent->id,
    ]);

    return [$user, $campaign, $crew, $game];
}

it('starts an aftermath flow and redirects to the wizard', function () {
    [$user, , , $game] = aftermathFixture();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.start', $game))
        ->assertRedirect();

    $aftermath = CampaignAftermath::first();
    expect($aftermath)->not->toBeNull();
    expect($aftermath->current_phase)->toBe(1);
    expect($aftermath->status)->toBe('open');
});

it('start is idempotent — second call returns the same aftermath', function () {
    [$user, , , $game] = aftermathFixture();

    $this->actingAs($user)->post(route('campaigns.aftermaths.start', $game));
    $this->actingAs($user)->post(route('campaigns.aftermaths.start', $game));

    expect(CampaignAftermath::count())->toBe(1);
});

it('renders the aftermath wizard for the crew owner', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.aftermaths.show', $aftermath))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Campaigns/Aftermath')
            ->where('aftermath.id', $aftermath->id)
            ->where('is_owner', true)
        );
});

it('blocks non-owners from viewing the wizard', function () {
    [, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
    ]);
    $other = amUser();

    $this->actingAs($other)
        ->get(route('campaigns.aftermaths.show', $aftermath))
        ->assertForbidden();
});

it('Phase 1 draws a hand sized by withdraw flag + schemes', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.draw-hand', $aftermath), [
            'completed_without_withdrawing' => true,
            'schemes_completed' => 2,
        ])
        ->assertRedirect();

    $aftermath->refresh();
    expect($aftermath->current_phase)->toBe(2);
    expect($aftermath->hand_drawn)->toHaveCount(3); // 1 + 2 schemes
});

it('Phase 2 Payday auto-adds scrip + advances to Barter', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 2,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.payday', $aftermath), [
            'vp' => 4, // → 2 scrip from VP
            'won' => true, // +1
            'crew_cr' => 1,
            'opponent_cr' => 3, // +2 (lower-rated)
        ])
        ->assertRedirect();

    $aftermath->refresh();
    expect($aftermath->current_phase)->toBe(3);
    expect($aftermath->scrip_earned)->toBe(5); // 2 + 1 + 2
    expect($crew->fresh()->scrip)->toBe(5);
});

it('Phase 3-5 advance-only stub bumps the phase without state writes', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance', $aftermath))
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(4);
});

it('Phase 6 applies injuries to arsenal models from the catalog', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $injury = Injury::factory()->create([
        'name' => 'Severe Amputation',
        'flip_value' => 3,
        'suit_pool' => 'pc',
        'annihilates_model' => false,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['arsenal_model_id' => $model->id, 'flip_value' => 3, 'suit_pool' => 'pc'],
            ],
        ])
        ->assertRedirect();

    expect(DB::table('campaign_arsenal_model_injuries')->where('campaign_arsenal_model_id', $model->id)->count())->toBe(1);
    expect($aftermath->fresh()->status)->toBe('locked');
});

it('Phase 6 annihilates a model that hits 3+ injuries', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $injury = Injury::factory()->create([
        'name' => 'Severe Amputation',
        'flip_value' => 3,
        'suit_pool' => 'pc',
        'annihilates_model' => false,
    ]);

    // Pre-seed two injuries so the third flip annihilates.
    DB::table('campaign_arsenal_model_injuries')->insert([
        ['campaign_arsenal_model_id' => $model->id, 'injury_catalog_id' => $injury->id, 'created_at' => now(), 'updated_at' => now()],
        ['campaign_arsenal_model_id' => $model->id, 'injury_catalog_id' => $injury->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['arsenal_model_id' => $model->id, 'flip_value' => 3, 'suit_pool' => 'pc'],
            ],
        ])
        ->assertRedirect();

    expect($model->fresh()->annihilated_at)->not->toBeNull();
});

it('Phase 6 immediately annihilates on Killed Off injury', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    Injury::factory()->killedOff()->create(['suit_pool' => 'pc']); // flip 13 / pc

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['arsenal_model_id' => $model->id, 'flip_value' => 13, 'suit_pool' => 'pc'],
            ],
        ])
        ->assertRedirect();

    expect($model->fresh()->annihilated_at)->not->toBeNull();
});

// ───── Phase 3 — Barter ─────

it('Phase 3 Barter writes equipment rows + deducts scrip + advances', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 10]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
    ]);
    $pistol = Equipment::factory()->alwaysAvailable()->create(['cc' => 1]);
    $helmet = Equipment::factory()->create(['br' => 3, 'cc' => 2]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), [
            'flip_value' => 5,
            'flip_suit' => 'ram',
            'is_red_joker' => false,
            'purchases' => [$pistol->id, $helmet->id],
        ])
        ->assertRedirect();

    expect(CampaignEquipment::where('campaign_crew_id', $crew->id)->count())->toBe(2);
    expect($crew->fresh()->scrip)->toBe(10 - 3);
    expect($aftermath->fresh()->current_phase)->toBe(4);
});

it('Phase 3 Barter rejects items not barterable at this flip', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 10]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
    ]);
    $expensive = Equipment::factory()->create(['br' => 8, 'cc' => 2]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), [
            'flip_value' => 5,
            'is_red_joker' => false,
            'purchases' => [$expensive->id],
        ])
        ->assertRedirect();

    expect(CampaignEquipment::where('campaign_crew_id', $crew->id)->count())->toBe(0);
    expect($aftermath->fresh()->current_phase)->toBe(3);
});

it('Phase 3 Barter rejects when scrip is insufficient', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 1]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
    ]);
    $item = Equipment::factory()->alwaysAvailable()->create(['cc' => 3]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), [
            'flip_value' => 5,
            'is_red_joker' => false,
            'purchases' => [$item->id],
        ])
        ->assertRedirect();

    expect(CampaignEquipment::where('campaign_crew_id', $crew->id)->count())->toBe(0);
});

it('Phase 3 Barter on red joker enables ttw_only items', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 10]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
    ]);
    $ttw = Equipment::factory()->thoseWhoThirst()->create(['br' => 2, 'cc' => 3]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), [
            'flip_value' => 5,
            'is_red_joker' => true,
            'purchases' => [$ttw->id],
        ])
        ->assertRedirect();

    expect(CampaignEquipment::where('campaign_crew_id', $crew->id)->count())->toBe(1);
});

it('Phase 3 Barter empty purchases still advances the phase', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), [
            'flip_value' => 7,
            'is_red_joker' => false,
            'purchases' => [],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(4);
});

// ───── Phase 5 — Back-Alley Doctor ─────

it('Phase 5 Doctor removes an injury on a removed-outcome flip', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 5]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $injury = Injury::factory()->create();
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $model->id,
        'injury_catalog_id' => $injury->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => 12, 'flip_value_max' => 13, 'outcome_kind' => 'removed',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => $pivotId, 'flip_value' => 12, 'suit_pool' => 'pc'],
            ],
        ])
        ->assertRedirect();

    expect(DB::table('campaign_arsenal_model_injuries')->where('id', $pivotId)->exists())->toBeFalse();
    expect($crew->fresh()->scrip)->toBe(4);
    expect($aftermath->fresh()->current_phase)->toBe(6);
});

it('Phase 5 Doctor on no_effect leaves the injury attached but still costs scrip', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 5]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $injury = Injury::factory()->create();
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $model->id,
        'injury_catalog_id' => $injury->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => 1, 'flip_value_max' => 8, 'outcome_kind' => 'no_effect',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => $pivotId, 'flip_value' => 5, 'suit_pool' => 'pc'],
            ],
        ])
        ->assertRedirect();

    expect(DB::table('campaign_arsenal_model_injuries')->where('id', $pivotId)->exists())->toBeTrue();
    expect($crew->fresh()->scrip)->toBe(4);
});

it('Phase 5 Doctor rejects when insufficient scrip', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 0]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $injury = Injury::factory()->create();
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $model->id,
        'injury_catalog_id' => $injury->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => $pivotId, 'flip_value' => 5, 'suit_pool' => 'pc'],
            ],
        ])
        ->assertRedirect();

    expect(DB::table('campaign_arsenal_model_injuries')->where('id', $pivotId)->exists())->toBeTrue();
    expect($aftermath->fresh()->current_phase)->toBe(5);
});

it('Phase 5 Doctor rejects bogus injury_pivot_id (validation, no scrip drain)', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 5]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => 99999, 'flip_value' => 5, 'suit_pool' => 'pc'],
            ],
        ])
        ->assertSessionHasErrors('attempts.0.injury_pivot_id');

    expect($crew->fresh()->scrip)->toBe(5); // not drained
    expect($aftermath->fresh()->current_phase)->toBe(5);
});

it("Phase 5 Doctor refuses an attempt targeting another crew's injury", function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 5]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);

    // An injury pivot row that exists but belongs to a DIFFERENT crew.
    $otherCrew = CampaignCrew::factory()->create();
    $otherModel = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $otherCrew->id]);
    $otherInjury = Injury::factory()->create();
    $foreignPivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $otherModel->id,
        'injury_catalog_id' => $otherInjury->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => $foreignPivotId, 'flip_value' => 5, 'suit_pool' => 'pc'],
            ],
        ])
        ->assertRedirect();

    expect($crew->fresh()->scrip)->toBe(5); // not drained
    expect(DB::table('campaign_aftermath_doctor')->where('campaign_aftermath_id', $aftermath->id)->count())->toBe(0);
    expect($aftermath->fresh()->current_phase)->toBe(5);
});

it('Phase 5 Doctor zero attempts still advances the phase', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(6);
});

// ───── Phase 4 — Advance Leader ─────

function buildLeaderFor(\App\Models\Campaign\CampaignCrew $crew, User $user): \App\Models\CustomCharacter
{
    return \App\Models\CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'TestLeader',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 14,
        'defense' => 5,
        'willpower' => 5,
        'speed' => 6,
        'base' => 30,
    ]);
}

it('Phase 4 fills XP boxes and writes leader_advancements rows', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $leader = buildLeaderFor($crew, $user);
    $attackMod = \App\Models\Campaign\AdvancementAttackMod::factory()->create();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'xp_earned' => 2,
            'advancements' => [
                [
                    'source_table' => 'attack_mod',
                    'catalog_id' => $attackMod->id,
                    'applied_to_action_index' => 0,
                    'position_in_xp_track' => 0,
                ],
            ],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(5);

    $track = $leader->fresh()->xp_track;
    expect($track)->not->toBeNull();
    expect($track[0]['filled'])->toBeTrue();
    expect($track[1]['filled'])->toBeTrue();
    expect($track[2]['filled'])->toBeFalse();

    $advance = \App\Models\Campaign\CampaignLeaderAdvancement::firstWhere('custom_character_id', $leader->id);
    expect($advance)->not->toBeNull();
    expect($advance->source_table)->toBe(\App\Enums\Campaign\AdvancementTableEnum::AttackMod);
    expect($advance->catalog_id)->toBe($attackMod->id);
    expect($advance->source_aftermath_id)->toBe($aftermath->id);
});

it('Phase 4 caps xp_earned at 3 via validation', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    buildLeaderFor($crew, $user);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'xp_earned' => 5,
            'advancements' => [],
        ])
        ->assertSessionHasErrors('xp_earned');
});

it('Phase 4 rejects when no current leader is built', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    // No leader created.

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'xp_earned' => 1,
            'advancements' => [],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(4);
});

it('Phase 4 with zero XP still advances the phase', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    buildLeaderFor($crew, $user);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'xp_earned' => 0,
            'advancements' => [],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(5);
});

it('lockAndAdvance is a no-op when the phase has already moved on', function () {
    // Simulates a double-click: the second submission arrives after the first
    // already advanced. The second call should land cleanly (no error, no
    // duplicate scrip / phase skip) and just redirect back to the wizard.
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 2,
        'hand_drawn' => [],
    ]);
    $startingScrip = $crew->scrip;

    $payload = ['vp' => 6, 'won' => true, 'crew_cr' => 0, 'opponent_cr' => 0];

    // First submission — advances 2 → 3 and pays out scrip.
    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.payday', $aftermath), $payload)
        ->assertRedirect();

    $afterFirst = $aftermath->fresh();
    expect($afterFirst->current_phase)->toBe(3);
    $scripAfterFirst = $crew->fresh()->scrip;
    expect($scripAfterFirst)->toBeGreaterThan($startingScrip);

    // Second submission with the stale aftermath model — lock guard rejects.
    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.payday', $aftermath), $payload)
        ->assertRedirect();

    // Phase didn't skip past Barter; scrip wasn't double-paid.
    expect($aftermath->fresh()->current_phase)->toBe(3);
    expect($crew->fresh()->scrip)->toBe($scripAfterFirst);
});

it('lockAndAdvance refuses mutations once the aftermath is locked', function () {
    // Mid-flow finalize race: confirm a barter POST is rejected after
    // determine-injuries has locked the aftermath, even if current_phase
    // would otherwise match the handler's expected slot.
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'status' => 'locked',
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), [
            'flip_value' => 5,
            'is_red_joker' => false,
            'purchases' => [],
        ])
        ->assertRedirect();

    // Phase did not advance — barter was refused because status === 'locked'.
    expect($aftermath->fresh()->current_phase)->toBe(3);
    expect($aftermath->fresh()->status)->toBe('locked');
});
