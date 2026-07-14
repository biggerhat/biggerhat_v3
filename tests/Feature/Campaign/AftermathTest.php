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

it('Phase 4 exposes the leader tag in the xp_track payload', function () {
    [$user, , $crew, $game] = aftermathFixture();

    \App\Models\CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Boss',
        'display_name' => 'Boss',
        'tag' => 'strategist',
        'archetype' => 'generalist',
        'health' => 8,
        'defense' => 5,
        'willpower' => 5,
        'speed' => 5,
    ]);

    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
    ]);

    $resp = $this->actingAs($user)->get(route('campaigns.aftermaths.show', $aftermath));
    $resp->assertOk();

    $xpTrack = $resp->viewData('page')['props']['xp_track'];
    expect($xpTrack['tag'])->toBe('strategist');
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

it('Phase 6 saves an optional story_entry, and leaves it null when omitted', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [],
            'story_entry' => 'A hard-fought win against the Ten Thunders.',
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->story_entry)->toBe('A hard-fought win against the Ten Thunders.');

    $aftermath2 = CampaignAftermath::factory()->create([
        'campaign_game_id' => CampaignGame::factory()->create([
            'campaign_id' => $game->campaign_id,
            'crew_a_id' => $crew->id,
            'crew_b_id' => $game->crew_b_id,
        ])->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath2), ['flips' => []])
        ->assertRedirect();

    expect($aftermath2->fresh()->story_entry)->toBeNull();
});

it('finalize (early-exit) also saves an optional story_entry', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.finalize', $aftermath), ['story_entry' => 'Closed early, but wanted to note the deployment.'])
        ->assertRedirect();

    $aftermath->refresh();
    expect($aftermath->status)->toBe('locked');
    expect($aftermath->story_entry)->toBe('Closed early, but wanted to note the deployment.');
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

it('Phase 6 red joker with an Ability-linked Lucky Miss permanently grants that Ability to the model', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $ability = \App\Models\Ability::factory()->create(['name' => 'Uncanny Luck']);
    $luckyMiss = \App\Models\Campaign\LuckyMiss::factory()->create(['flip_value' => 8, 'is_doppelganger' => false, 'ability_id' => $ability->id]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['arsenal_model_id' => $model->id, 'is_red_joker' => true, 'lucky_miss_flip_value' => 8],
            ],
        ])
        ->assertRedirect();

    expect($model->fresh()->gained_lucky_miss_ids)->toEqual([$luckyMiss->id]);
    expect($model->fresh()->gainedAbilities->pluck('name')->all())->toBe(['Uncanny Luck']);
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

it('Phase 6 black joker defects to a player-chosen crew when the game has no opponent (solo)', function () {
    [$user, $campaign, $crew, $game] = aftermathFixture();
    // Solo-logged games have no opponent on the game row.
    $game->update(['crew_b_id' => null]);
    $destination = CampaignCrew::factory()->create(['campaign_id' => $campaign->id]);

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
                ['arsenal_model_id' => $model->id, 'is_black_joker' => true, 'traitor_target_crew_id' => $destination->id],
            ],
        ])
        ->assertRedirect();

    expect($model->fresh()->annihilated_at)->not->toBeNull();
    $defector = CampaignArsenalModel::where('campaign_crew_id', $destination->id)->where('acquired_via', 'traitor')->first();
    expect($defector)->not->toBeNull();
    expect($defector->character_id)->toBe($model->character_id);
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

it('Phase 3 Barter rejects a Those Who Thirst item from the normal purchases list', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 10]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
    ]);
    // Those Who Thirst items are a separate red-joker sub-table pick (pg 30),
    // not purchasable via the normal barter list.
    $ttw = Upgrade::factory()->campaignEquipmentTtw()->create(['campaign_br' => 2, 'campaign_cc' => 3]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), [
            'purchases' => [$ttw->id],
        ])
        ->assertRedirect();

    expect(CampaignEquipment::where('campaign_crew_id', $crew->id)->count())->toBe(0);
    expect($aftermath->fresh()->current_phase)->toBe(3);
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
    $result = BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => 12, 'flip_value_max' => 13, 'outcome_kind' => 'removed',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => $pivotId, 'result_id' => $result->id],
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
    $result = BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => 9, 'flip_value_max' => 9, 'outcome_kind' => 'removed_and_reflip',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                [
                    'injury_pivot_id' => $pivotId,
                    'result_id' => $result->id,
                    'added_injury_upgrade_id' => $reflipped->id,
                ],
            ],
        ])
        ->assertRedirect();

    // Original gone, reflipped injury attached in its place.
    expect(DB::table('campaign_arsenal_model_injuries')->where('id', $pivotId)->exists())->toBeFalse();
    $remaining = DB::table('campaign_arsenal_model_injuries')->where('campaign_arsenal_model_id', $model->id)->pluck('injury_upgrade_id')->all();
    expect($remaining)->toEqual([$reflipped->id]);
});

it('Phase 5 Doctor "How many fingers do you need?" (the real seeded flip-9 row) swaps the injury just like "Oops" does', function () {
    // Regression check for a reported QA bug: "How many fingers do you
    // need?" (RemovedAndReflip, pg 33) was suspected of not prompting for a
    // replacement injury the way "Oops" (AddedInjury) does. Uses the actual
    // catalog row CampaignCatalogSeeder writes — not just a factory row with
    // a matching outcome_kind — so this traces directly to the reported name.
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
    $result = BackAlleyDoctorResult::factory()->create([
        'name' => 'How many fingers do you need?',
        'body' => 'Annihilate the chosen injury, then reflip on the injury chart for a new one.',
        'flip_value_min' => 9,
        'flip_value_max' => 9,
        'outcome_kind' => \App\Enums\Campaign\BackAlleyDoctorOutcomeEnum::RemovedAndReflip->value,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [[
                'injury_pivot_id' => $pivotId,
                'result_id' => $result->id,
                'added_injury_upgrade_id' => $reflipped->id,
            ]],
        ])
        ->assertRedirect();

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
    $result = BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => null, 'flip_value_max' => null, 'is_red_joker' => true, 'outcome_kind' => 'lucky_miss_reflip',
    ]);
    $luckyMiss = \App\Models\Campaign\LuckyMiss::factory()->create(['flip_value' => 4, 'is_doppelganger' => false]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => $pivotId, 'result_id' => $result->id, 'lucky_miss_flip_value' => 4],
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
    $result = BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => 1, 'flip_value_max' => 8, 'outcome_kind' => 'no_effect',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => $pivotId, 'result_id' => $result->id],
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

    $result = BackAlleyDoctorResult::factory()->create(['flip_value_min' => 1, 'flip_value_max' => 8, 'outcome_kind' => 'no_effect']);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => $pivotId, 'result_id' => $result->id],
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

    $result = BackAlleyDoctorResult::factory()->create(['flip_value_min' => 1, 'flip_value_max' => 8, 'outcome_kind' => 'no_effect']);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => $foreignPivotId, 'result_id' => $result->id],
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
    $attackMod = \App\Models\Campaign\AdvancementAttackMod::factory()->create();

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

it('Phase 4 Summoning Advancement can be routed to the Totem instead of the Leader', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $leader = buildLeaderFor($crew, $user);
    $totem = \App\Models\CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_totem' => true,
        'current' => true,
        'name' => 'Test Totem',
        'display_name' => 'Test Totem',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 6, 'defense' => 5, 'willpower' => 5, 'speed' => 6, 'base' => 30,
    ]);
    $summoning = \App\Models\Action::factory()->create([
        'name' => 'Test Summoning',
        'game_mode_type' => \App\Enums\GameModeTypeEnum::Campaign->value,
        'campaign_advancement_kind' => 'summoning',
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
                    'applied_to_custom_character_id' => $totem->id,
                    // Tier-3 box (the default XP track has a 3 at index 4).
                    'position_in_xp_track' => 4,
                ],
            ],
        ])
        ->assertRedirect();

    $recorded = \App\Models\Campaign\CampaignLeaderAdvancement::sole();
    expect($recorded->applied_to_custom_character_id)->toBe($totem->id);
    expect(collect($totem->fresh()->actions)->pluck('name'))->toContain('Test Summoning');
    expect(collect($leader->fresh()->actions)->pluck('name'))->not->toContain('Test Summoning');
});

it('Phase 4 Totem Advancement no longer requires a flip value (flip-gating removed for now)', function () {
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

    // No flip_value submitted at all, and it doesn't match the totem's own
    // campaign_totem_flip_value (7) — still accepted, since the picker no
    // longer asks for or enforces a flip.
    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'totem',
                'catalog_id' => $totemTemplate->id,
                // Totem is tier 3 → must sit on a tier-3 box (index 4).
                'position_in_xp_track' => 4,
            ]],
        ])
        ->assertRedirect();
    expect($aftermath->fresh()->current_phase)->toBe(5);
});

it('Phase 4 Totem Advancement carries the template ability body under the description key', function () {
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
        'name' => 'Wisp',
        'display_name' => 'Wisp',
        'faction' => \App\Enums\FactionEnum::Arcanists->value,
        'health' => 4,
        'defense' => 4,
        'willpower' => 4,
        'speed' => 5,
        'base' => 30,
    ]);
    $ability = \App\Models\Ability::factory()->create(['name' => 'Test Totem Ability', 'description' => 'Does a totem thing.']);
    $totemTemplate->campaignTotemAbilities()->attach($ability->id);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'totem',
                'catalog_id' => $totemTemplate->id,
                'position_in_xp_track' => 4,
            ]],
        ])
        ->assertRedirect();

    $totem = \App\Models\CustomCharacter::where('is_campaign_totem', true)->where('campaign_crew_id', $crew->id)->firstOrFail();
    expect($totem->abilities[0]['name'])->toBe('Test Totem Ability');
    expect($totem->abilities[0]['description'])->toBe('Does a totem thing.');
    expect($totem->abilities[0])->not->toHaveKey('body');
});

it('Phase 4 Totem Advancement inherits the leader\'s keywords', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $leaderKeywords = [['id' => 1, 'name' => 'Ten Thunders']];
    $leader = buildLeaderFor($crew, $user);
    $leader->update(['keywords' => $leaderKeywords]);

    $totemTemplate = \App\Models\CustomCharacter::create([
        'user_id' => $user->id,
        'is_campaign_totem_template' => true,
        'campaign_totem_flip_value' => 7,
        'name' => 'Wisp',
        'display_name' => 'Wisp',
        'faction' => \App\Enums\FactionEnum::Arcanists->value,
        'health' => 4, 'defense' => 4, 'willpower' => 4, 'speed' => 5, 'base' => 30,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'totem',
                'catalog_id' => $totemTemplate->id,
                'flip_value' => 7,
                'position_in_xp_track' => 4,
            ]],
        ])
        ->assertRedirect();

    $totem = \App\Models\CustomCharacter::query()
        ->where('campaign_crew_id', $crew->id)
        ->where('is_campaign_totem', true)
        ->where('current', true)
        ->first();
    expect($totem)->not->toBeNull();
    expect($totem->keywords)->toBe($leaderKeywords);
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

it('Phase 4 Any Joker applies the real free-chosen action to the leader', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $keyword = \App\Models\Keyword::factory()->create();
    $leader = buildLeaderFor($crew, $user);
    $leader->update(['keywords' => [['id' => $keyword->id, 'name' => $keyword->name]]]);

    $sourceChar = \App\Models\Character::factory()->create(['cost' => 8, 'station' => null]);
    $sourceChar->keywords()->attach($keyword);
    $sourceAction = \App\Models\Action::factory()->create(['name' => 'Borrowed Strike']);
    $sourceChar->actions()->attach($sourceAction);

    $anyJokerRow = \App\Models\Campaign\AdvancementAction::factory()->anyJoker()->create();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'action',
                'catalog_id' => $anyJokerRow->id,
                // Tier-2 box (default XP track has a 2 at index 2).
                'position_in_xp_track' => 2,
                'free_choice' => ['source_id' => $sourceAction->id, 'source_character_id' => $sourceChar->id],
            ]],
        ])
        ->assertRedirect();

    $leader->refresh();
    expect(collect($leader->actions)->pluck('name'))->toContain('Borrowed Strike');
    $advancement = \App\Models\Campaign\CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->firstOrFail();
    expect($advancement->catalog_core_id)->toBe($sourceAction->id);
});

it('Phase 4 Any Joker rejects a source model outside the leader\'s keywords', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $leaderKeyword = \App\Models\Keyword::factory()->create();
    $otherKeyword = \App\Models\Keyword::factory()->create();
    $leader = buildLeaderFor($crew, $user);
    $leader->update(['keywords' => [['id' => $leaderKeyword->id, 'name' => $leaderKeyword->name]]]);

    // Source model does NOT share the leader's keyword.
    $sourceChar = \App\Models\Character::factory()->create(['cost' => 8, 'station' => null]);
    $sourceChar->keywords()->attach($otherKeyword);
    $sourceAction = \App\Models\Action::factory()->create();
    $sourceChar->actions()->attach($sourceAction);

    $anyJokerRow = \App\Models\Campaign\AdvancementAction::factory()->anyJoker()->create();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'action',
                'catalog_id' => $anyJokerRow->id,
                'position_in_xp_track' => 2,
                'free_choice' => ['source_id' => $sourceAction->id, 'source_character_id' => $sourceChar->id],
            ]],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(4); // rejected, didn't advance
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
    $attackMod = \App\Models\Campaign\AdvancementAttackMod::factory()->create(['flip_value' => 11]);

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

it('Phase 4 applies an Attack Mod trigger to an Equipment-granted action via the advance-leader payload', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $leader = buildLeaderFor($crew, $user);
    $equipment = \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crew->id]);
    $action = \App\Models\Action::factory()->create(['type' => 'attack', 'stat' => 5]);
    $equipment->catalog->actions()->attach($action->id, ['is_signature_action' => false]);
    $trigger = \App\Models\Campaign\AdvancementAttackMod::factory()->create(['flip_value' => 5]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'attack_mod',
                'catalog_id' => $trigger->id,
                'position_in_xp_track' => 0,
                'flip_value' => 13,
                'from_equipment_id' => $equipment->id,
                'applied_to_action_id' => $action->id,
            ]],
        ])
        ->assertRedirect();

    $advancement = \App\Models\Campaign\CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->firstOrFail();
    expect($advancement->from_equipment_id)->toBe($equipment->id);
    expect($advancement->applied_to_action_id)->toBe($action->id);
});

it('Phase 4 Crew Card advancement stacks onto CampaignCrewCardAdvancement without touching the starter effect', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    buildLeaderFor($crew, $user);

    $starterEffect = \App\Models\Campaign\CampaignCrewCard::factory()->create(['name' => 'Starter Effect']);
    $crew->update(['crew_card_effect_id' => $starterEffect->id]);

    // A generic (master_id null) row — one of the book-native starting crew
    // card effects (pg 15-16), not tied to any master. Rulebook confirms this
    // path needs no master attribution at all, unlike a master-tied pick.
    $borrowedEffect = \App\Models\Campaign\CampaignCrewCard::factory()->create(['name' => 'Borrowed Effect']);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'crew_card',
                'catalog_id' => $borrowedEffect->id,
                // Tier-4 box (the default XP track has a 4 at index 6).
                'position_in_xp_track' => 6,
            ]],
        ])
        ->assertRedirect();

    $crew->refresh();
    expect($crew->crew_card_effect_id)->toBe($starterEffect->id); // untouched
    $stacked = \App\Models\Campaign\CampaignCrewCardAdvancement::where('campaign_crew_id', $crew->id)->get();
    expect($stacked)->toHaveCount(1);
    expect($stacked->first()->crew_card_effect_id)->toBe($borrowedEffect->id);
    expect($stacked->first()->source_master_id)->toBeNull();
    expect($stacked->first()->source_master_type)->toBeNull();
});

it('Phase 4 Crew Card advancement requiring a token choice stores the resolved pick, and rejects one outside the pool', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $keyword = \App\Models\Keyword::factory()->create();
    buildLeaderFor($crew, $user);
    $crew->update(['keyword_1_id' => $keyword->id]);

    // A crew-card (crew-domain) upgrade sharing the crew's keyword, with a token on it.
    $token = \App\Models\Token::factory()->create(['name' => 'Fast']);
    $crewCardUpgrade = \App\Models\Upgrade::factory()->create([
        'domain' => \App\Enums\UpgradeDomainTypeEnum::Crew->value,
        'game_mode_type' => \App\Enums\GameModeTypeEnum::Standard->value,
    ]);
    $crewCardUpgrade->keywords()->attach($keyword);
    $crewCardUpgrade->tokens()->attach($token);

    $borrowedEffect = \App\Models\Campaign\CampaignCrewCard::factory()->create(['requires_token_choice' => true]);

    // A token not on any keyword crew card is rejected.
    $stray = \App\Models\Token::factory()->create();
    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'crew_card',
                'catalog_id' => $borrowedEffect->id,
                'position_in_xp_track' => 6,
                'crew_card_choice' => ['id' => $stray->id],
            ]],
        ])
        ->assertRedirect();
    expect($aftermath->fresh()->current_phase)->toBe(4);
    expect(\App\Models\Campaign\CampaignCrewCardAdvancement::count())->toBe(0);

    // A valid, in-pool token is stored resolved to { type, id, name }.
    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'crew_card',
                'catalog_id' => $borrowedEffect->id,
                'position_in_xp_track' => 6,
                'crew_card_choice' => ['id' => $token->id],
            ]],
        ])
        ->assertRedirect();

    $stacked = \App\Models\Campaign\CampaignCrewCardAdvancement::where('campaign_crew_id', $crew->id)->firstOrFail();
    expect($stacked->crew_card_choice)->toMatchArray(['type' => 'token', 'id' => $token->id, 'name' => 'Fast']);
});

it('Phase 4 Crew Card advancement rejects a master-tied row whose master is outside the leader\'s keywords', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $leaderKeyword = \App\Models\Keyword::factory()->create();
    $otherKeyword = \App\Models\Keyword::factory()->create();
    $leader = buildLeaderFor($crew, $user);
    $leader->update(['keywords' => [['id' => $leaderKeyword->id, 'name' => $leaderKeyword->name]]]);

    $master = \App\Models\Character::factory()->create(['station' => \App\Enums\CharacterStationEnum::Master->value]);
    $master->keywords()->attach($otherKeyword);
    $borrowedEffect = \App\Models\Campaign\CampaignCrewCard::factory()->forOfficialMaster($master)->create();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'crew_card',
                'catalog_id' => $borrowedEffect->id,
                'position_in_xp_track' => 6,
            ]],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(4);
    expect(\App\Models\Campaign\CampaignCrewCardAdvancement::count())->toBe(0);
});

it('advancement_catalogs.crew_card exposes the generated card image', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    buildLeaderFor($crew, $user);

    $row = \App\Models\Campaign\CampaignCrewCard::factory()->create([
        'name' => 'Imaged Effect',
        'front_image' => 'campaign-crew-cards/123/front.png',
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.aftermaths.show', $aftermath))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('advancement_catalogs.crew_card', function ($rows) use ($row) {
            $match = collect($rows)->firstWhere('id', $row->id);

            return $match && $match['front_image'] === 'campaign-crew-cards/123/front.png';
        }));
});

it('Phase 4 Crew Card advancement derives source_master_id from the catalog row, not the client', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $keyword = \App\Models\Keyword::factory()->create();
    $leader = buildLeaderFor($crew, $user);
    $leader->update(['keywords' => [['id' => $keyword->id, 'name' => $keyword->name]]]);

    $master = \App\Models\Character::factory()->create(['station' => \App\Enums\CharacterStationEnum::Master->value]);
    $master->keywords()->attach($keyword);
    $borrowedEffect = \App\Models\Campaign\CampaignCrewCard::factory()->forOfficialMaster($master)->create();

    // No free_choice submitted at all — the row's own master_id is authoritative.
    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'crew_card',
                'catalog_id' => $borrowedEffect->id,
                'position_in_xp_track' => 6,
                'free_choice' => null,
            ]],
        ])
        ->assertRedirect();

    $stacked = \App\Models\Campaign\CampaignCrewCardAdvancement::where('campaign_crew_id', $crew->id)->get();
    expect($stacked)->toHaveCount(1);
    expect($stacked->first()->source_master_id)->toBe($master->id);
    expect($stacked->first()->source_master_type)->toBe(\App\Models\Character::class);
});

it('Phase 4 Crew Card advancement derives source_master from a custom-built Campaign Leader catalog row', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $keyword = \App\Models\Keyword::factory()->create();
    $leader = buildLeaderFor($crew, $user);
    $leader->update(['keywords' => [['id' => $keyword->id, 'name' => $keyword->name]]]);

    // An unrelated ally's custom-built Leader sharing the same keyword — the
    // crew card it's printed on can be borrowed from without belonging to
    // this crew.
    $customMaster = \App\Models\CustomCharacter::create([
        'user_id' => $user->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'AllyLeader',
        'tag' => 'ally',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 14,
        'defense' => 5,
        'willpower' => 5,
        'speed' => 6,
        'base' => 30,
        'keywords' => [['id' => $keyword->id, 'name' => $keyword->name]],
    ]);
    $borrowedEffect = \App\Models\Campaign\CampaignCrewCard::factory()->forCustomMaster($customMaster)->create();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'crew_card',
                'catalog_id' => $borrowedEffect->id,
                'position_in_xp_track' => 6,
                'free_choice' => null,
            ]],
        ])
        ->assertRedirect();

    $stacked = \App\Models\Campaign\CampaignCrewCardAdvancement::where('campaign_crew_id', $crew->id)->get();
    expect($stacked)->toHaveCount(1);
    expect($stacked->first()->source_master_id)->toBe($customMaster->id);
    expect($stacked->first()->source_master_type)->toBe(\App\Models\CustomCharacter::class);
    expect($stacked->first()->sourceMaster)->toBeInstanceOf(\App\Models\CustomCharacter::class);
});

it('Phase 4 Crew Card advancement ignores a client-submitted master that does not match the catalog row', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $keyword = \App\Models\Keyword::factory()->create();
    $leader = buildLeaderFor($crew, $user);
    $leader->update(['keywords' => [['id' => $keyword->id, 'name' => $keyword->name]]]);

    $realMaster = \App\Models\Character::factory()->create(['station' => \App\Enums\CharacterStationEnum::Master->value]);
    $realMaster->keywords()->attach($keyword);
    $borrowedEffect = \App\Models\Campaign\CampaignCrewCard::factory()->forOfficialMaster($realMaster)->create();

    // A second, unrelated eligible master the client tries to spoof as the source.
    $spoofedMaster = \App\Models\Character::factory()->create(['station' => \App\Enums\CharacterStationEnum::Master->value]);
    $spoofedMaster->keywords()->attach($keyword);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'crew_card',
                'catalog_id' => $borrowedEffect->id,
                'position_in_xp_track' => 6,
                'free_choice' => ['source_character_id' => $spoofedMaster->id],
            ]],
        ])
        ->assertRedirect();

    $stacked = \App\Models\Campaign\CampaignCrewCardAdvancement::where('campaign_crew_id', $crew->id)->get();
    expect($stacked)->toHaveCount(1);
    expect($stacked->first()->source_master_id)->toBe($realMaster->id);
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
    $result = BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => 12, 'flip_value_max' => 13, 'outcome_kind' => 'removed',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [['injury_pivot_id' => $pivotId, 'result_id' => $result->id]],
        ])
        ->assertRedirect();

    $log = DB::table('campaign_aftermath_doctor')->where('campaign_aftermath_id', $aftermath->id)->first();
    expect($log)->not->toBeNull();
    expect($log->target_injury_id)->toBeNull();
});

it('Aftermath show pre-fills payday + draw-hand from the logged game scoring', function () {
    [$user, , $crew, $game] = aftermathFixture(); // crew is crew_a
    $game->update([
        'vp_a' => 6, 'vp_b' => 3,
        'schemes_completed_a' => 2,
        'winner_crew_id' => $crew->id,
        'cr_a' => 4, 'cr_b' => 1,
    ]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 1,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.aftermaths.show', $aftermath))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('prefill.vp_self', 6)
            ->where('prefill.vp_opponent', 3)
            ->where('prefill.schemes_completed', 2)
            ->where('prefill.won', true)
            ->where('prefill.crew_cr', 4)
            ->where('prefill.opponent_cr', 1)
            ->etc());
});

it('Phase 6 Miraculous Recovery spares a leader on a self-annihilating injury the first time', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $leader = buildLeaderFor($crew, $user);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $killedOff = Upgrade::factory()->campaignInjuryKilledOff()->create();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['custom_character_id' => $leader->id, 'injury_upgrade_id' => $killedOff->id],
            ],
        ])
        ->assertRedirect();

    // First annihilation attempt is spared via Miraculous Recovery — leader
    // survives and stays current; the reprieve is now spent.
    $fresh = $leader->fresh();
    expect($fresh->annihilated_at)->toBeNull();
    expect($fresh->current)->toBeTrue();
    expect($fresh->miraculous_recovery_used)->toBeTrue();
});

it('Phase 6 annihilates a leader on the second self-annihilating injury (Miraculous Recovery already spent)', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $leader = buildLeaderFor($crew, $user);
    $leader->update(['miraculous_recovery_used' => true]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $killedOff = Upgrade::factory()->campaignInjuryKilledOff()->create();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['custom_character_id' => $leader->id, 'injury_upgrade_id' => $killedOff->id],
            ],
        ])
        ->assertRedirect();

    $fresh = $leader->fresh();
    expect($fresh->annihilated_at)->not->toBeNull();
    expect($fresh->current)->toBeFalse();
});

it('Phase 6 rejects a black joker (Traitor) flip on a leader — surfaces a reflip message instead of silently no-op', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $leader = buildLeaderFor($crew, $user);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.determine-injuries', $aftermath), [
            'flips' => [
                ['custom_character_id' => $leader->id, 'is_black_joker' => true],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('message');

    // Phase must not have advanced — the submission was rejected outright.
    expect($aftermath->fresh()->current_phase)->toBe(6);
    expect($leader->fresh()->annihilated_at)->toBeNull();
});

it('Phase 6 a cheated red joker on a standard model does not grant a Lucky Miss reflip', function () {
    [$user, , $crew, $game] = aftermathFixture();
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
                ['arsenal_model_id' => $model->id, 'is_red_joker' => true, 'cheated' => true, 'lucky_miss_flip_value' => 4],
            ],
        ])
        ->assertRedirect();

    // No injury attached (avoided), but no Lucky Miss bonus either.
    expect($model->fresh()->gained_lucky_miss_ids ?? [])->toBeEmpty();
    expect(DB::table('campaign_arsenal_model_injuries')->where('campaign_arsenal_model_id', $model->id)->count())->toBe(0);
});

it('Phase 5 crew_injuries payload includes a leader/totem injury pivot', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $leader = buildLeaderFor($crew, $user);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);
    $injury = Upgrade::factory()->campaignInjury()->create(['name' => 'Test Injury']);
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => null,
        'custom_character_id' => $leader->id,
        'injury_upgrade_id' => $injury->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.aftermaths.show', $aftermath))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('crew_injuries', 1)
            ->where('crew_injuries.0.pivot_id', $pivotId)
            ->where('crew_injuries.0.custom_character_id', $leader->id)
            ->where('crew_injuries.0.display_name', 'TestLeader')
            ->where('crew_injuries.0.injury_name', 'Test Injury')
            ->etc());
});

it('Phase 5 Doctor can target and resolve a leader/totem injury', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 5]);
    $leader = buildLeaderFor($crew, $user);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);
    $injury = Upgrade::factory()->campaignInjury()->create();
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => null,
        'custom_character_id' => $leader->id,
        'injury_upgrade_id' => $injury->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $result = BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => 1, 'flip_value_max' => 8, 'outcome_kind' => 'removed',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => $pivotId, 'result_id' => $result->id],
            ],
        ])
        ->assertRedirect();

    expect(DB::table('campaign_arsenal_model_injuries')->where('id', $pivotId)->exists())->toBeFalse();
    $log = DB::table('campaign_aftermath_doctor')->where('campaign_aftermath_id', $aftermath->id)->first();
    expect($log->target_custom_character_id)->toBe($leader->id);
    expect($log->target_arsenal_model_id)->toBeNull();
});

it('Phase 5 Doctor rejects an attempt targeting another crew\'s leader injury', function () {
    [$user, $campaign, $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 5]);
    $otherCrew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id]);
    $otherLeader = buildLeaderFor($otherCrew, $user);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);
    $injury = Upgrade::factory()->campaignInjury()->create();
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => null,
        'custom_character_id' => $otherLeader->id,
        'injury_upgrade_id' => $injury->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $result = BackAlleyDoctorResult::factory()->create(['flip_value_min' => 1, 'flip_value_max' => 8, 'outcome_kind' => 'no_effect']);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [
                ['injury_pivot_id' => $pivotId, 'result_id' => $result->id],
            ],
        ])
        ->assertRedirect();

    expect(DB::table('campaign_aftermath_doctor')->where('campaign_aftermath_id', $aftermath->id)->count())->toBe(0);
});

it('Phase 3 Barter rejects a duplicate purchase of a unique item the crew already owns', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 10]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
    ]);
    $unique = Upgrade::factory()->campaignEquipmentAlwaysAvailable()->create(['campaign_is_unique' => true, 'campaign_cc' => 2]);
    CampaignEquipment::factory()->create([
        'campaign_crew_id' => $crew->id,
        'equipment_upgrade_id' => $unique->id,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.barter', $aftermath), [
            'purchases' => [$unique->id],
        ])
        ->assertRedirect();

    expect(CampaignEquipment::where('campaign_crew_id', $crew->id)->where('equipment_upgrade_id', $unique->id)->count())->toBe(1);
    expect($aftermath->fresh()->current_phase)->toBe(3);
});

it('CustomCharacter::attemptAnnihilation spends Miraculous Recovery the first time, annihilates the second', function () {
    $user = amUser();
    $campaign = Campaign::factory()->create();
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $leader = buildLeaderFor($crew, $user);

    expect($leader->attemptAnnihilation())->toBeTrue();
    expect($leader->fresh()->miraculous_recovery_used)->toBeTrue();
    expect($leader->fresh()->annihilated_at)->toBeNull();

    expect($leader->attemptAnnihilation())->toBeFalse();
    expect($leader->fresh()->annihilated_at)->not->toBeNull();
    expect($leader->fresh()->current)->toBeFalse();
});

it('CampaignArsenalModel::copyForCampaign carries the granted_keyword_id to the defector copy', function () {
    $user = amUser();
    $campaign = Campaign::factory()->create();
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $targetCrew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id]);
    $keyword = \App\Models\Keyword::factory()->create();
    $model = CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crew->id,
        'granted_keyword_id' => $keyword->id,
    ]);

    $copy = $model->copyForCampaign($targetCrew->id, 'traitor');

    expect($copy->granted_keyword_id)->toBe($keyword->id);
});

it('Phase 6 review screen: phase_summary rolls up what Phases 1-5 already committed', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $leader = buildLeaderFor($crew, $user);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => ['size' => 3],
        'scrip_earned' => 4,
    ]);

    $blade = \App\Models\Upgrade::factory()->campaignEquipment()->create(['name' => 'Test Blade', 'campaign_cc' => 3]);
    \App\Models\Campaign\CampaignEquipment::factory()->create([
        'campaign_crew_id' => $crew->id,
        'equipment_upgrade_id' => $blade->id,
        'source' => 'barter',
        'acquired_aftermath_id' => $aftermath->id,
    ]);

    $attackMod = \App\Models\Campaign\AdvancementAttackMod::factory()->create(['name' => 'Test Attack Mod']);
    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_aftermath_id' => $aftermath->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::AttackMod->value,
        'advancement_catalog_id' => $attackMod->id,
        'applied_to_action_index' => -1,
        'position_in_xp_track' => 3,
        'acquired_at' => now(),
    ]);

    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    DB::table('campaign_aftermath_doctor')->insert([
        'campaign_aftermath_id' => $aftermath->id,
        'target_arsenal_model_id' => $model->id,
        'target_custom_character_id' => null,
        'target_injury_id' => null,
        'flip_value' => null,
        'outcome' => 'removed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.aftermaths.show', $aftermath))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('phase_summary.hand_size', 3)
            ->where('phase_summary.scrip_earned', 4)
            ->where('phase_summary.barter.0.name', 'Test Blade')
            ->where('phase_summary.barter_total_cc', 3)
            ->where('phase_summary.advancements.0.table', 'Attack Mod')
            ->where('phase_summary.advancements.0.name', 'Test Attack Mod')
            ->where('phase_summary.advancements.0.target', 'TestLeader')
            ->has('phase_summary.doctor_attempts', 1)
            ->where('phase_summary.doctor_attempts.0.outcome', 'Removed')
            ->etc());
});

it('Phase 6 review screen: phase_summary resolves the Totem/Equipment target for a rerouted advancement', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $leader = buildLeaderFor($crew, $user);
    $totem = \App\Models\CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_totem' => true,
        'current' => true,
        'name' => 'Test Totem',
        'display_name' => 'Test Totem',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 6, 'defense' => 5, 'willpower' => 5, 'speed' => 6, 'base' => 30,
    ]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $attackMod = \App\Models\Campaign\AdvancementAttackMod::factory()->create(['name' => 'Totem-routed Trigger']);
    \App\Models\Campaign\CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_aftermath_id' => $aftermath->id,
        'source_table' => \App\Enums\Campaign\AdvancementTableEnum::AttackMod->value,
        'advancement_catalog_id' => $attackMod->id,
        'applied_to_custom_character_id' => $totem->id,
        'applied_to_action_index' => -1,
        'position_in_xp_track' => 3,
        'acquired_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.aftermaths.show', $aftermath))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('phase_summary.advancements.0.name', 'Totem-routed Trigger')
            ->where('phase_summary.advancements.0.target', 'Test Totem')
            ->etc());
});

it('phase_summary is null outside Phase 6', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->get(route('campaigns.aftermaths.show', $aftermath))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('phase_summary', null));
});

it('goBack from Phase 2 clears the drawn hand and returns to Phase 1', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 2,
        'hand_drawn' => ['size' => 2],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    $fresh = $aftermath->fresh();
    expect($fresh->current_phase)->toBe(1);
    expect($fresh->hand_drawn)->toBeNull();
});

it('goBack from Phase 2 pops a Phase 1 skip marker instead of leaving it stale', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 1,
        'hand_drawn' => null,
    ]);

    $this->actingAs($user)->post(route('campaigns.aftermaths.advance', $aftermath))->assertRedirect();
    expect($aftermath->fresh()->current_phase)->toBe(2);
    expect($aftermath->fresh()->hand_used)->toHaveCount(1);

    $this->actingAs($user)->post(route('campaigns.aftermaths.back', $aftermath))->assertRedirect();

    $fresh = $aftermath->fresh();
    expect($fresh->current_phase)->toBe(1);
    expect($fresh->hand_used)->toBe([]);
});

it('goBack from Phase 3 refunds the scrip Payday granted and returns to Phase 2', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 10]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => [],
        'scrip_earned' => 4,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    $fresh = $aftermath->fresh();
    expect($fresh->current_phase)->toBe(2);
    expect($fresh->scrip_earned)->toBe(0);
    expect($crew->fresh()->scrip)->toBe(6);
});

it('goBack from Phase 4 deletes the barter purchases and refunds their cc, returning to Phase 3', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 2]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $blade = \App\Models\Upgrade::factory()->campaignEquipment()->create(['campaign_cc' => 3]);
    \App\Models\Campaign\CampaignEquipment::factory()->create([
        'campaign_crew_id' => $crew->id,
        'equipment_upgrade_id' => $blade->id,
        'source' => 'barter',
        'acquired_aftermath_id' => $aftermath->id,
    ]);
    // A purchase from a DIFFERENT aftermath must not be touched or refunded.
    $otherGame = \App\Models\Campaign\CampaignGame::factory()->create([
        'campaign_id' => $game->campaign_id,
        'crew_a_id' => $crew->id,
    ]);
    $otherAftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $otherGame->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'hand_drawn' => [],
    ]);
    $unrelated = \App\Models\Campaign\CampaignEquipment::factory()->create([
        'campaign_crew_id' => $crew->id,
        'source' => 'barter',
        'acquired_aftermath_id' => $otherAftermath->id,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    $fresh = $aftermath->fresh();
    expect($fresh->current_phase)->toBe(3);
    expect($crew->fresh()->scrip)->toBe(5);
    expect(\App\Models\Campaign\CampaignEquipment::where('acquired_aftermath_id', $aftermath->id)->exists())->toBeFalse();
    expect(\App\Models\Campaign\CampaignEquipment::whereKey($unrelated->id)->exists())->toBeTrue();
});

it('goBack refuses once the aftermath is locked', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 6,
        'status' => 'locked',
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(6);
    expect($aftermath->fresh()->status)->toBe('locked');
});

it('goBack chains correctly across repeated calls without double-reverting a single phase', function () {
    // Each click of "Back" legitimately steps back one more phase — unlike a
    // phase-specific submit endpoint, goBack has no single "owning" phase to
    // stale-guard against, so two calls in a row should chain (3 -> 2 -> 1),
    // not silently no-op the second one.
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 10]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 3,
        'hand_drawn' => ['size' => 2],
        'scrip_earned' => 4,
    ]);

    $this->actingAs($user)->post(route('campaigns.aftermaths.back', $aftermath))->assertRedirect();
    expect($aftermath->fresh()->current_phase)->toBe(2);
    expect($crew->fresh()->scrip)->toBe(6);

    $this->actingAs($user)->post(route('campaigns.aftermaths.back', $aftermath))->assertRedirect();
    $fresh = $aftermath->fresh();
    expect($fresh->current_phase)->toBe(1);
    expect($fresh->hand_drawn)->toBeNull();
    // Scrip refund only happened once, on the phase-3->2 step — not doubled.
    expect($crew->fresh()->scrip)->toBe(6);
});

it('goBack refuses on Phase 1 (nothing before it)', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 1,
        'hand_drawn' => null,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(1);
});

it('goBack from Phase 5 deletes the Phase 4 advancements and unfills exactly the XP boxes it filled', function () {
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
            // play (+1) + lost (+1) = 2 XP — fills boxes 0 and 1.
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => true,
            'advancements' => [[
                'source_table' => 'attack_mod',
                'catalog_id' => $attackMod->id,
                'applied_to_action_index' => 0,
                'position_in_xp_track' => 0,
            ]],
        ])
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(5);
    expect($aftermath->fresh()->xp_earned)->toBe(2);
    expect(\App\Models\Campaign\CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(1);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    $fresh = $aftermath->fresh();
    expect($fresh->current_phase)->toBe(4);
    expect($fresh->xp_earned)->toBeNull();
    expect(\App\Models\Campaign\CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(0);

    $track = $leader->fresh()->xp_track;
    expect($track[0]['filled'])->toBeFalse();
    expect($track[1]['filled'])->toBeFalse();
});

it('goBack from Phase 5 restores a Skl Boost target action to its prior value', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $leader = buildLeaderFor($crew, $user);
    $leader->update(['actions' => [
        ['name' => 'Test Attack', 'type' => 'attack', 'category' => 'attack', 'is_signature' => false, 'stone_cost' => 0, 'stat' => 5, 'triggers' => []],
    ]]);
    $sklBoost = \App\Models\Campaign\AdvancementAttackMod::factory()->sklBoost(5, 6)->create();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => true,
            'advancements' => [[
                'source_table' => 'attack_mod',
                'catalog_id' => $sklBoost->id,
                'applied_to_action_index' => 0,
                'position_in_xp_track' => 0,
            ]],
        ])
        ->assertRedirect();

    expect($leader->fresh()->actions[0]['stat'])->toBe(6);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(4);
    expect($leader->fresh()->actions[0]['stat'])->toBe(5);
});

it('goBack refuses once current_phase exceeds 6 (defensive — should never happen in practice)', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 7,
        'hand_drawn' => [],
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(7);
});

it('goBack from Phase 6 restores a removed injury and refunds the doctor scrip', function () {
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
    $result = BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => 12, 'flip_value_max' => 13, 'outcome_kind' => 'removed',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [['injury_pivot_id' => $pivotId, 'result_id' => $result->id]],
        ])
        ->assertRedirect();
    expect($crew->fresh()->scrip)->toBe(4);
    expect(DB::table('campaign_arsenal_model_injuries')->where('campaign_arsenal_model_id', $model->id)->exists())->toBeFalse();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    expect($aftermath->fresh()->current_phase)->toBe(5);
    expect($crew->fresh()->scrip)->toBe(5);
    expect(DB::table('campaign_aftermath_doctor')->where('campaign_aftermath_id', $aftermath->id)->count())->toBe(0);
    $restored = DB::table('campaign_arsenal_model_injuries')->where('campaign_arsenal_model_id', $model->id)->first();
    expect($restored)->not->toBeNull();
    expect($restored->injury_upgrade_id)->toBe($injury->id);
});

it('goBack from Phase 6 undoes flip 9 — restores the original injury and deletes the reflipped one', function () {
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
    $result = BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => 9, 'flip_value_max' => 9, 'outcome_kind' => 'removed_and_reflip',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [[
                'injury_pivot_id' => $pivotId,
                'result_id' => $result->id,
                'added_injury_upgrade_id' => $reflipped->id,
            ]],
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    $remaining = DB::table('campaign_arsenal_model_injuries')->where('campaign_arsenal_model_id', $model->id)->pluck('injury_upgrade_id')->all();
    expect($remaining)->toEqual([$original->id]);
});

it('goBack from Phase 6 deletes an "Oops" added injury, leaving the original attached', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 5]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    $original = Upgrade::factory()->campaignInjury()->create();
    $added = Upgrade::factory()->campaignInjury()->create();
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $model->id,
        'injury_upgrade_id' => $original->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $result = BackAlleyDoctorResult::factory()->create(['outcome_kind' => 'added_injury']);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [[
                'injury_pivot_id' => $pivotId,
                'result_id' => $result->id,
                'added_injury_upgrade_id' => $added->id,
            ]],
        ])
        ->assertRedirect();
    expect(DB::table('campaign_arsenal_model_injuries')->where('campaign_arsenal_model_id', $model->id)->count())->toBe(2);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    $remaining = DB::table('campaign_arsenal_model_injuries')->where('campaign_arsenal_model_id', $model->id)->pluck('injury_upgrade_id')->all();
    expect($remaining)->toEqual([$original->id]);
});

it('goBack from Phase 6 strips a gained characteristic and restores the removed injury', function () {
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
    $result = BackAlleyDoctorResult::factory()->create(['outcome_kind' => 'gained_undead']);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [['injury_pivot_id' => $pivotId, 'result_id' => $result->id]],
        ])
        ->assertRedirect();
    expect($model->fresh()->gained_characteristics)->toEqual(['Undead']);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    expect($model->fresh()->gained_characteristics)->toEqual([]);
    $restored = DB::table('campaign_arsenal_model_injuries')->where('campaign_arsenal_model_id', $model->id)->first();
    expect($restored)->not->toBeNull();
    expect($restored->injury_upgrade_id)->toBe($injury->id);
});

it('goBack from Phase 6 pops the applied Lucky Miss and restores the removed injury', function () {
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
    $result = BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => null, 'flip_value_max' => null, 'is_red_joker' => true, 'outcome_kind' => 'lucky_miss_reflip',
    ]);
    $luckyMiss = \App\Models\Campaign\LuckyMiss::factory()->create(['flip_value' => 4, 'is_doppelganger' => false]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [['injury_pivot_id' => $pivotId, 'result_id' => $result->id, 'lucky_miss_flip_value' => 4]],
        ])
        ->assertRedirect();
    expect($model->fresh()->gained_lucky_miss_ids)->toEqual([$luckyMiss->id]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    expect($model->fresh()->gained_lucky_miss_ids)->toEqual([]);
    $restored = DB::table('campaign_arsenal_model_injuries')->where('campaign_arsenal_model_id', $model->id)->first();
    expect($restored)->not->toBeNull();
});

it('goBack from Phase 6 deletes a Doppelganger copy created by an any-joker Lucky Miss', function () {
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
    $result = BackAlleyDoctorResult::factory()->create([
        'flip_value_min' => null, 'flip_value_max' => null, 'is_red_joker' => true, 'outcome_kind' => 'lucky_miss_reflip',
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [['injury_pivot_id' => $pivotId, 'result_id' => $result->id, 'lucky_miss_is_joker' => true]],
        ])
        ->assertRedirect();
    $copyCount = CampaignArsenalModel::where('campaign_crew_id', $crew->id)->where('acquired_via', 'doppelganger')->count();
    expect($copyCount)->toBe(1);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->where('acquired_via', 'doppelganger')->count())->toBe(0);
});

it('goBack from Phase 6 un-annihilates a model whose 3rd injury came from this Doctor attempt', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 5]);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);
    // One pre-existing injury from an earlier, unrelated aftermath.
    DB::table('campaign_arsenal_model_injuries')->insert([
        'campaign_arsenal_model_id' => $model->id, 'injury_upgrade_id' => Upgrade::factory()->campaignInjury()->create()->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $treated = Upgrade::factory()->campaignInjury()->create();
    $treatedPivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'campaign_arsenal_model_id' => $model->id,
        'injury_upgrade_id' => $treated->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $thirdInjury = Upgrade::factory()->campaignInjury()->create();
    $result = BackAlleyDoctorResult::factory()->create(['outcome_kind' => 'added_injury']);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [[
                'injury_pivot_id' => $treatedPivotId,
                'result_id' => $result->id,
                'added_injury_upgrade_id' => $thirdInjury->id,
            ]],
        ])
        ->assertRedirect();
    expect($model->fresh()->annihilated_at)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    expect($model->fresh()->annihilated_at)->toBeNull();
});

it('goBack from Phase 6 restores a removed leader/totem injury', function () {
    [$user, , $crew, $game] = aftermathFixture();
    $crew->update(['scrip' => 5]);
    $leader = buildLeaderFor($crew, $user);
    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 5,
        'hand_drawn' => [],
    ]);
    $injury = Upgrade::factory()->campaignInjury()->create();
    $pivotId = DB::table('campaign_arsenal_model_injuries')->insertGetId([
        'custom_character_id' => $leader->id,
        'injury_upgrade_id' => $injury->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $result = BackAlleyDoctorResult::factory()->create(['outcome_kind' => 'removed']);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.doctor', $aftermath), [
            'attempts' => [['injury_pivot_id' => $pivotId, 'result_id' => $result->id]],
        ])
        ->assertRedirect();
    expect(DB::table('campaign_arsenal_model_injuries')->where('custom_character_id', $leader->id)->exists())->toBeFalse();

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.back', $aftermath))
        ->assertRedirect();

    $restored = DB::table('campaign_arsenal_model_injuries')->where('custom_character_id', $leader->id)->first();
    expect($restored)->not->toBeNull();
    expect($restored->injury_upgrade_id)->toBe($injury->id);
});
