<?php

use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentStandingsService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->payload = fn (array $extra = []) => [
        'name' => 'Custom Event',
        'encounter_size' => 50,
        'planned_rounds' => 3,
        'season' => Tournament::factory()->make()->season->value,
        'event_date' => now()->addWeek()->toDateString(),
        ...$extra,
    ];
});

it('stores enabled variants, dropping those switched off and keeping parameter values', function () {
    $this->actingAs($this->owner)->post(route('tournaments.store'), ($this->payload)([
        'variants' => ['alternating_leaders' => true, 'blind_battle' => false, 'bans' => 1],
    ]))->assertRedirect();

    expect(Tournament::latest('id')->first()->variants)->toBe(['alternating_leaders' => true, 'bans' => 1]);
});

it('rejects a variant that does not exist for the ruleset, or an out-of-range parameter', function () {
    $this->actingAs($this->owner)->postJson(route('tournaments.store'), ($this->payload)(['variants' => ['close_objectives' => true]]))
        ->assertStatus(422)->assertJsonValidationErrors(['variants.close_objectives']);

    $this->actingAs($this->owner)->postJson(route('tournaments.store'), ($this->payload)(['variants' => ['bans' => 99]]))
        ->assertStatus(422)->assertJsonValidationErrors(['variants.bans']);
});

it('offers each ruleset its own variants', function () {
    $this->actingAs($this->owner)->post(route('tournaments.store'), ($this->payload)([
        'ruleset' => 'tos_fields_of_glory', 'variants' => ['decisive_victory' => true],
    ]))->assertRedirect();

    expect(Tournament::latest('id')->first()->activeVariants())->toBe(['decisive_victory' => true]);
});

it('ignores stored variants that are no longer in the catalog', function () {
    $t = Tournament::factory()->create(['variants' => ['alternating_leaders' => true, 'removed_variant' => true]]);

    expect($t->activeVariants())->toBe(['alternating_leaders' => true]);
});

it('applies scoring overrides on top of the ruleset', function () {
    $this->actingAs($this->owner)->post(route('tournaments.store'), ($this->payload)([
        'ruleset' => 'custom',
        'scoring_config' => ['win_tp' => 5, 'tie_tp' => 2, 'tiebreakers' => ['vp', 'diff']],
    ]))->assertRedirect();

    $t = Tournament::latest('id')->first();
    expect($t->scoringConfig())->winTp->toBe(5)->tieTp->toBe(2)->lossTp->toBe(0);
    expect($t->tiebreakerKeys())->toBe(['vp', 'diff']);
});

it('rejects invalid scoring overrides', function () {
    $this->actingAs($this->owner)->postJson(route('tournaments.store'), ($this->payload)([
        'scoring_config' => ['win_tp' => -1, 'unknown_key' => 3, 'tiebreakers' => ['sos', 'sos']],
    ]))->assertStatus(422)->assertJsonValidationErrors(['scoring_config', 'scoring_config.win_tp', 'scoring_config.tiebreakers.0']);
});

it('standings follow the overridden scoring', function () {
    $t = Tournament::factory()->active()->create(['ruleset' => 'custom', 'scoring_config' => ['win_tp' => 7]]);
    $r = \App\Models\TournamentRound::factory()->for($t)->create();
    [$a, $b] = \App\Models\TournamentPlayer::factory()->for($t)->count(2)->create()->all();
    \App\Models\TournamentGame::factory()->for($r, 'round')->withScore(5, 2)->create(['player_one_id' => $a->id, 'player_two_id' => $b->id]);

    $s = collect(app(TournamentStandingsService::class)->compute($t))->keyBy('player_id');

    expect($s[$a->id]['total_tp'])->toBe(7);
});

it('edits variants and scoring before the event starts, but locks them afterwards', function () {
    $t = Tournament::factory()->create(['creator_id' => $this->owner->id]);

    $this->actingAs($this->owner)->putJson(route('tournaments.update', $t), [
        'variants' => ['bans' => 2], 'scoring_config' => ['win_tp' => 4],
    ])->assertOk();
    expect($t->fresh())->variants->toBe(['bans' => 2])->scoring_config->toBe(['win_tp' => 4]);

    $t->update(['status' => 'active']);
    $this->actingAs($this->owner)->putJson(route('tournaments.update', $t), ['scoring_config' => ['win_tp' => 9]])
        ->assertStatus(422);
    expect($t->fresh()->scoring_config)->toBe(['win_tp' => 4]);
});

it('clears customizations when empty values are sent', function () {
    $t = Tournament::factory()->create(['creator_id' => $this->owner->id, 'variants' => ['bans' => 2], 'scoring_config' => ['win_tp' => 4]]);

    $this->actingAs($this->owner)->putJson(route('tournaments.update', $t), ['variants' => [], 'scoring_config' => []])->assertOk();

    expect($t->fresh())->variants->toBeNull()->scoring_config->toBeNull();
});

it('flags customized scoring on the manage payload but not for legacy default bye values', function () {
    $legacy = Tournament::factory()->create(['creator_id' => $this->owner->id, 'bye_tp' => 3, 'bye_diff' => 4, 'bye_vp' => 6]);
    $custom = Tournament::factory()->create(['creator_id' => $this->owner->id, 'scoring_config' => ['win_tp' => 4], 'variants' => ['bans' => 1]]);

    $this->actingAs($this->owner)->get(route('tournaments.manage', $legacy))
        ->assertInertia(fn (Assert $page) => $page->where('ruleset.scoring_customized', false)->where('ruleset.active_variants', []));
    $this->actingAs($this->owner)->get(route('tournaments.manage', $custom))
        ->assertInertia(fn (Assert $page) => $page->where('ruleset.scoring_customized', true)->where('ruleset.active_variants', ['bans' => 1]));
});

it('carries variants and scoring when copying an event', function () {
    $source = Tournament::factory()->create(['creator_id' => $this->owner->id, 'variants' => ['bans' => 2], 'scoring_config' => ['win_tp' => 4]]);

    $this->actingAs($this->owner)->get(route('tournaments.create', ['copy' => $source->uuid]))
        ->assertInertia(fn (Assert $page) => $page->where('template.variants', ['bans' => 2])->where('template.scoring_config', ['win_tp' => 4]));
});

it('runs the Custom ruleset with no Malifaux requirements', function () {
    $this->actingAs($this->owner)->post(route('tournaments.store'), ($this->payload)(['ruleset' => 'custom']))->assertRedirect();
    $t = Tournament::latest('id')->first();

    expect($t->rulesetDefinition())->declaration->toBe('none')->supportsTracker->toBeFalse();
    expect($t->rulesetDefinition()->scenarioFields)->toBe([]);
});
