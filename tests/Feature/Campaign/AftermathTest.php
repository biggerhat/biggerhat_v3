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
use App\Models\Trigger;
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

it('Phase 1 records the entitled hand size (player draws their own cards)', function () {
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
    expect($aftermath->hand_drawn)->toBe(['size' => 3]); // 1 + 2 schemes — no cards dealt
});

it('Phase 2 Payday auto-adds scrip + advances to Barter', function () {
    [$user, , $crew, $game] = aftermathFixture();
    // CR snapshot lives on the campaign_games row at game-creation time —
    // payday derives it server-side rather than trusting client input.
    $game->update(['cr_a' => 1, 'cr_b' => 3]);
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
        ])
        ->assertRedirect();

    $aftermath->refresh();
    expect($aftermath->current_phase)->toBe(3);
    expect($aftermath->scrip_earned)->toBe(5); // 2 + 1 + 2 (CR diff)
    expect($crew->fresh()->scrip)->toBe(5);
});

it('Phase 2 Payday applies turn 3+ Strategic Withdrawal VP adjustment', function () {
    [$user, , $crew, $game] = aftermathFixture();
    // Crew withdrew on turn 3 with 4 VP and opponent had 3 VP — per pg 20,
    // opponent counts as scoring +1 higher, so withdrawing crew's payday
    // VP becomes their own 4 (book interpretation: their own scoring
    // doesn't change, only the opponent's relative VP for tie-breaking).
    // Our adjuster keeps withdrew_vp at 4 in this case.
    $game->update([
        'cr_a' => 0,
        'cr_b' => 0,
        'withdrew_crew_id' => $crew->id,
        'withdrew_turn' => 3,
        'vp_b' => 3,
    ]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 2,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.payday', $aftermath), [
            'vp' => 4,
            'won' => false,
        ])
        ->assertRedirect();

    $aftermath->refresh();
    expect($aftermath->scrip_earned)->toBe(2); // ceil(4/3) = 2
});

it('Phase 2 Payday bumps the non-withdrawing crew when the opponent withdrew turn 3+', function () {
    [$user, , $crew, $game] = aftermathFixture();
    // The OPPONENT (crew_b) withdrew on turn 3 with 5 VP; this crew had 3 VP.
    // Per pg 20 this crew counts as scoring +1 over the withdrawer → 6 VP.
    $game->update([
        'cr_a' => 0,
        'cr_b' => 0,
        'withdrew_crew_id' => $game->crew_b_id,
        'withdrew_turn' => 3,
        'vp_b' => 5,
    ]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 2,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.payday', $aftermath), [
            'vp' => 3,
            'won' => false,
        ])
        ->assertRedirect();

    // Effective VP 6 → ceil(6/3) = 2 (vs ceil(3/3) = 1 without the bump).
    expect($aftermath->fresh()->scrip_earned)->toBe(2);
});

it('start jumps to Phase 6 on turn 1-2 Strategic Withdrawal', function () {
    [$user, , $crew, $game] = aftermathFixture();
    // Pg 20: crew withdrawing on turn ≤ 2 receives no VP, no barter, no hand —
    // they skip phases 1-5 entirely and go straight to injury flips.
    $game->update([
        'withdrew_crew_id' => $crew->id,
        'withdrew_turn' => 2,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.start', $game))
        ->assertRedirect();

    $aftermath = CampaignAftermath::first();
    expect($aftermath)->not->toBeNull();
    expect($aftermath->current_phase)->toBe(6);
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
    $injury = Upgrade::factory()->campaignInjury()->create([
        'name' => 'Severe Amputation',
        'campaign_flip_value' => 3,
        'campaign_suit_pool' => 'pc',
        'campaign_annihilates_model' => false,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['arsenal_model_id' => $model->id, 'injury_upgrade_id' => $injury->id],
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

    // Three DISTINCT injuries — duplicates of the same injury are ignored
    // (pg 34), so only different injuries stack toward the 3-injury annihilation
    // threshold. Pre-seed two, then flip the third.
    $injuryA = Upgrade::factory()->campaignInjury()->create(['campaign_flip_value' => 3, 'campaign_suit_pool' => 'pc', 'campaign_annihilates_model' => false]);
    $injuryB = Upgrade::factory()->campaignInjury()->create(['campaign_flip_value' => 4, 'campaign_suit_pool' => 'pc', 'campaign_annihilates_model' => false]);
    $injuryC = Upgrade::factory()->campaignInjury()->create(['campaign_flip_value' => 5, 'campaign_suit_pool' => 'pc', 'campaign_annihilates_model' => false]);

    DB::table('campaign_arsenal_model_injuries')->insert([
        ['campaign_arsenal_model_id' => $model->id, 'injury_upgrade_id' => $injuryA->id, 'created_at' => now(), 'updated_at' => now()],
        ['campaign_arsenal_model_id' => $model->id, 'injury_upgrade_id' => $injuryB->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['arsenal_model_id' => $model->id, 'injury_upgrade_id' => $injuryC->id],
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
    $killedOff = Upgrade::factory()->campaignInjuryKilledOff()->create(['campaign_suit_pool' => 'pc', 'campaign_flip_value' => 13]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['arsenal_model_id' => $model->id, 'injury_upgrade_id' => $killedOff->id],
            ],
        ])
        ->assertRedirect();

    expect($model->fresh()->annihilated_at)->not->toBeNull();
});

it('Phase 6 red joker sends the model to the Lucky Miss table instead of an injury', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $luckyMiss = \App\Models\Campaign\LuckyMiss::factory()->create(['flip_value' => 8, 'is_doppelganger' => false]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['arsenal_model_id' => $model->id, 'is_red_joker' => true, 'lucky_miss_flip_value' => 8],
            ],
        ])
        ->assertRedirect();

    // No injury attached; the Lucky Miss upgrade is recorded on the model.
    expect($model->fresh()->injuries()->count())->toBe(0);
    expect($model->fresh()->gained_lucky_miss_ids)->toEqual([$luckyMiss->id]);
});

it('Phase 6 black joker (Traitor) defects the model to the opponent crew with its injuries', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $injury = Upgrade::factory()->campaignInjury()->create();
    DB::table('campaign_arsenal_model_injuries')->insert([
        'campaign_arsenal_model_id' => $model->id, 'injury_upgrade_id' => $injury->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['arsenal_model_id' => $model->id, 'is_black_joker' => true],
            ],
        ])
        ->assertRedirect();

    // Original annihilated; a copy now sits in the opponent's arsenal with the injury.
    expect($model->fresh()->annihilated_at)->not->toBeNull();
    $defector = CampaignArsenalModel::where('campaign_crew_id', $game->crew_b_id)->where('acquired_via', 'traitor')->first();
    expect($defector)->not->toBeNull();
    expect($defector->character_id)->toBe($model->character_id);
    expect($defector->injuries()->pluck('injury_upgrade_id')->all())->toEqual([$injury->id]);
});

it('Phase 6 black joker (Traitor) just annihilates when there is no opponent crew', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $game->update(['crew_b_id' => null]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['arsenal_model_id' => $model->id, 'is_black_joker' => true],
            ],
        ])
        ->assertRedirect();

    expect($model->fresh()->annihilated_at)->not->toBeNull();
    expect(CampaignArsenalModel::where('acquired_via', 'traitor')->count())->toBe(0);
});

it('Phase 6 Doppelganger adds a limit-exempt copy to this crew with its injuries', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $injury = Upgrade::factory()->campaignInjury()->create();
    DB::table('campaign_arsenal_model_injuries')->insert([
        'campaign_arsenal_model_id' => $model->id, 'injury_upgrade_id' => $injury->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['arsenal_model_id' => $model->id, 'is_red_joker' => true, 'lucky_miss_is_joker' => true],
            ],
        ])
        ->assertRedirect();

    // Original survives (Close Call = no injury); a copy joins the same crew.
    expect($model->fresh()->annihilated_at)->toBeNull();
    $copy = CampaignArsenalModel::where('campaign_crew_id', $crew->id)->where('acquired_via', 'doppelganger')->first();
    expect($copy)->not->toBeNull();
    expect($copy->ignored_for_limits)->toBeTrue();
    expect($copy->injuries()->pluck('injury_upgrade_id')->all())->toEqual([$injury->id]);
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
    $pistol = Upgrade::factory()->campaignEquipmentAlwaysAvailable()->create(['campaign_cc' => 1]);
    $helmet = Upgrade::factory()->campaignEquipment()->create(['campaign_br' => 3, 'campaign_cc' => 2]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), [
            'purchases' => [$pistol->id, $helmet->id],
        ])
        ->assertRedirect();

    expect(CampaignEquipment::where('campaign_crew_id', $crew->id)->count())->toBe(2);
    expect($crew->fresh()->scrip)->toBe(10 - 3);
    expect($aftermath->fresh()->current_phase)->toBe(4);
});

it('Phase 3 Barter buys any catalog item by id — flip/BR/suit is resolved at the table', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 10]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
    ]);
    // An item the old flip/suit gate would have rejected is now purchasable —
    // the player makes barter flips physically and just records the buy.
    $helmet = Upgrade::factory()->campaignEquipment()->create([
        'campaign_br' => 8,
        'campaign_cc' => 2,
        'campaign_pool_suit_a' => 'ram',
        'campaign_pool_suit_b' => 'crow',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), [
            'purchases' => [$helmet->id],
        ])
        ->assertRedirect();

    expect(CampaignEquipment::where('campaign_crew_id', $crew->id)->count())->toBe(1);
    expect($crew->fresh()->scrip)->toBe(8);
    expect($aftermath->fresh()->current_phase)->toBe(4);
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
    $item = Upgrade::factory()->campaignEquipmentAlwaysAvailable()->create(['campaign_cc' => 3]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), [
            'purchases' => [$item->id],
        ])
        ->assertRedirect();

    expect(CampaignEquipment::where('campaign_crew_id', $crew->id)->count())->toBe(0);
});

it('Phase 3 Barter records a Those Who Thirst item with source joker', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 10]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
    ]);
    $ttw = Upgrade::factory()->campaignEquipmentTtw()->create(['campaign_br' => 2, 'campaign_cc' => 3]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), [
            'purchases' => [$ttw->id],
        ])
        ->assertRedirect();

    $eq = CampaignEquipment::where('campaign_crew_id', $crew->id)->first();
    expect($eq)->not->toBeNull();
    expect($eq->source)->toBe('joker');
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
    $injury = Upgrade::factory()->campaignInjury()->create();
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $model->id,
        'injury_upgrade_id' => $injury->id,
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

it('Phase 5 Doctor flip 9 removes the original injury and attaches the reflipped one', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 5]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $original = Upgrade::factory()->campaignInjury()->create(['campaign_suit_pool' => 'pc', 'campaign_flip_value' => 3]);
    $reflipped = Upgrade::factory()->campaignInjury()->create(['campaign_suit_pool' => 'te', 'campaign_flip_value' => 7]);
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $model->id,
        'injury_upgrade_id' => $original->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => 9, 'flip_value_max' => 9, 'outcome_kind' => 'removed_and_reflip',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                [
                    'injury_pivot_id' => $pivotId,
                    'flip_value' => 9,
                    'suit_pool' => 'pc',
                    'added_injury_flip_value' => 7,
                    'added_injury_suit_pool' => 'te',
                ],
            ],
        ])
        ->assertRedirect();

    // Original gone, reflipped injury attached in its place.
    expect(DB::table('campaign_arsenal_model_injuries')->where('id', $pivotId)->exists())->toBeFalse();
    $remaining = DB::table('campaign_arsenal_model_injuries')->where('campaign_arsenal_model_id', $model->id)->pluck('injury_upgrade_id')->all();
    expect($remaining)->toEqual([$reflipped->id]);
});

it('Phase 5 Doctor red joker annihilates the injury and applies a Lucky Miss', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 5]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $injury = Upgrade::factory()->campaignInjury()->create();
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $model->id,
        'injury_upgrade_id' => $injury->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => null, 'flip_value_max' => null, 'is_red_joker' => true, 'outcome_kind' => 'lucky_miss_reflip',
    ]);
    $luckyMiss = \App\Models\Campaign\LuckyMiss::factory()->create(['flip_value' => 4, 'is_doppelganger' => false]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => $pivotId, 'is_red_joker' => true, 'lucky_miss_flip_value' => 4],
            ],
        ])
        ->assertRedirect();

    expect(DB::table('campaign_arsenal_model_injuries')->where('id', $pivotId)->exists())->toBeFalse();
    expect($model->fresh()->gained_lucky_miss_ids)->toEqual([$luckyMiss->id]);
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
    $injury = Upgrade::factory()->campaignInjury()->create();
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $model->id,
        'injury_upgrade_id' => $injury->id,
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
    $injury = Upgrade::factory()->campaignInjury()->create();
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $model->id,
        'injury_upgrade_id' => $injury->id,
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
    $otherInjury = Upgrade::factory()->campaignInjury()->create();
    $foreignPivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $otherModel->id,
        'injury_upgrade_id' => $otherInjury->id,
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

function buildLeaderFor(\App\Models\Campaign\CampaignCrew $crew, User $user, string $tag = 'bruiser'): \App\Models\CustomCharacter
{
    return \App\Models\CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'TestLeader',
        'tag' => $tag,
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
    $attackMod = Trigger::factory()->campaignAdvancementAttack()->create();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            // play (+1) + lost (+1) = 2 XP. Bruiser bonus stays off here.
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => true,
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
    expect($advance->catalog_core_id)->toBe($attackMod->id);
    expect($advance->source_aftermath_id)->toBe($aftermath->id);
});

it('Phase 4 caps XP at 3 and ignores the off-tag bonus', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    // Bruiser leader: the Strategist fact must be ignored, and the total caps
    // at 3 (play + bruiser kill + lost), never 4.
    $leader = buildLeaderFor($crew, $user, 'bruiser');

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => true,
            'strategist_interacted' => true,
            'lost' => true,
            'advancements' => [],
        ])
        ->assertRedirect();

    $filled = collect($leader->fresh()->xp_track)->where('filled', true)->count();
    expect($filled)->toBe(3);
    expect($aftermath->fresh()->current_phase)->toBe(5);
});

it('Phase 4 ignores the Bruiser bonus for a Strategist leader', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    // Strategist leader claiming a Bruiser kill — only play (+1) should count.
    $leader = buildLeaderFor($crew, $user, 'strategist');

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => true,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [],
        ])
        ->assertRedirect();

    $filled = collect($leader->fresh()->xp_track)->where('filled', true)->count();
    expect($filled)->toBe(1);
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
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(4);
});

it('Phase 4 rejects a second Summoning Advancement for the same leader', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $leader = buildLeaderFor($crew, $user);
    $summoning = \App\Models\Action::factory()->create([
        'game_mode_type' => \App\Enums\GameModeTypeEnum::Campaign->value,
        'campaign_advancement_kind' => 'summoning',
    ]);
    // Pre-existing Summoning advancement from an earlier aftermath.
    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::Summoning->value,
        'catalog_core_id' => $summoning->id,
        'applied_to_action_index' => -1,
        'position_in_xp_track' => 0,
        'acquired_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [
                [
                    'source_table' => 'summoning',
                    'catalog_id' => $summoning->id,
                    // Tier-3 box (the default XP track has a 3 at index 4) so the
                    // rejection is the summoning-once rule, not the tier gate.
                    'position_in_xp_track' => 4,
                ],
            ],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(4); // didn't advance
    expect(\App\Models\Campaign\CampaignLeaderAdvancement::count())->toBe(1); // no second row
});

it('Phase 4 enforces exact flip-value match on Totem Advancement', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    buildLeaderFor($crew, $user);
    $totemTemplate = \App\Models\CustomCharacter::create([
        'user_id' => $user->id,
        'is_campaign_totem_template' => true,
        'campaign_totem_flip_value' => 7,
        'name' => 'Wisp',
        'display_name' => 'Wisp',
        'faction' => \App\Enums\FactionEnum::Arcanists->value,
        'health' => 4,
        'defense' => 4,
        'willpower' => 4,
        'speed' => 5,
        'base' => 30,
    ]);

    // Flipped 7, picked totem with flip_value 7 — should be accepted.
    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'totem',
                'catalog_id' => $totemTemplate->id,
                'flip_value' => 7,
                // Totem is tier 3 → must sit on a tier-3 box (index 4).
                'position_in_xp_track' => 4,
            ]],
        ])
        ->assertRedirect();
    expect($aftermath->fresh()->current_phase)->toBe(5);

    // Now try the wrong flip-value (chose totem 7 but flipped 5) — reject.
    \Illuminate\Support\Facades\DB::table('campaign_aftermaths')
        ->where('id', $aftermath->id)
        ->update(['current_phase' => 4]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'totem',
                'catalog_id' => $totemTemplate->id,
                'flip_value' => 5,
                'position_in_xp_track' => 4,
            ]],
        ])
        ->assertRedirect();
    expect($aftermath->fresh()->current_phase)->toBe(4);
});

it('Phase 4 with only the play point advances the phase', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $leader = buildLeaderFor($crew, $user);

    // No bonuses — every game grants the +1 play point (pg 31), so exactly one
    // box fills and the phase advances.
    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(5);
    expect(collect($leader->fresh()->xp_track)->where('filled', true)->count())->toBe(1);
});

it('Phase 4 rejects an advancement whose tier exceeds the XP box', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    buildLeaderFor($crew, $user);
    $summoning = \App\Models\Action::factory()->create([
        'game_mode_type' => \App\Enums\GameModeTypeEnum::Campaign->value,
        'campaign_advancement_kind' => 'summoning',
    ]);

    // Summoning is tier 3 but index 0 is a tier-1 box → reject.
    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'summoning',
                'catalog_id' => $summoning->id,
                'position_in_xp_track' => 0,
            ]],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(4);
    expect(\App\Models\Campaign\CampaignLeaderAdvancement::count())->toBe(0);
});

it('Phase 4 rejects an Attack Mod whose flip value exceeds the flipped card', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    buildLeaderFor($crew, $user);
    // Attack mod requiring an 11, picked against a flip of 5 → rejected (pg 38).
    $attackMod = Trigger::factory()->campaignAdvancementAttack()->create(['campaign_flip_value' => 11]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'attack_mod',
                'catalog_id' => $attackMod->id,
                'position_in_xp_track' => 0,
                'flip_value' => 5,
            ]],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(4);
    expect(\App\Models\Campaign\CampaignLeaderAdvancement::count())->toBe(0);
});

it('Phase 4 rejects a Totem Advancement when the crew already has a totem', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    buildLeaderFor($crew, $user);
    // Existing current totem on the crew.
    \App\Models\CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_totem' => true,
        'current' => true,
        'name' => 'Existing Totem',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 4, 'defense' => 4, 'willpower' => 4, 'speed' => 5, 'base' => 30,
    ]);
    $template = \App\Models\CustomCharacter::create([
        'user_id' => $user->id,
        'is_campaign_totem_template' => true,
        'campaign_totem_flip_value' => 7,
        'name' => 'Wisp',
        'display_name' => 'Wisp',
        'health' => 4, 'defense' => 4, 'willpower' => 4, 'speed' => 5, 'base' => 30,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'totem',
                'catalog_id' => $template->id,
                'flip_value' => 7,
                'position_in_xp_track' => 4,
            ]],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(4);
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
            'purchases' => [],
        ])
        ->assertRedirect();

    // Phase did not advance — barter was refused because status === 'locked'.
    expect($aftermath->fresh()->current_phase)->toBe(3);
    expect($aftermath->fresh()->status)->toBe('locked');
});

it('Phase 3 Barter can be skipped with no flip and no scrip', function () {
    [$user, , $crew, $game] = aftermathFixture(); // crew scrip = 0
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), ['purchases' => []])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(4);
    expect($crew->fresh()->scrip)->toBe(0);
    $this->assertDatabaseCount('campaign_aftermath_barter', 0);
});

it('Phase 5 Doctor logs a removed outcome with a null injury reference (no dangling FK)', function () {
    // Tests run with FKs off; in MySQL the old code inserted the just-deleted
    // injury id into the audit log and hit a foreign-key violation (500). The
    // fix stores null for removed outcomes — assert that here.
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 5]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $injury = Upgrade::factory()->campaignInjury()->create();
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $model->id,
        'injury_upgrade_id' => $injury->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => 12, 'flip_value_max' => 13, 'outcome_kind' => 'removed',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [['injury_pivot_id' => $pivotId, 'flip_value' => 12, 'suit_pool' => 'pc']],
        ])
        ->assertRedirect();

    $log = DB::table('campaign_aftermath_doctor')->where('campaign_aftermath_id', $aftermath->id)->first();
    expect($log)->not->toBeNull();
    expect($log->target_injury_id)->toBeNull();
});
