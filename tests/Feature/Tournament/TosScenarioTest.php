<?php

use App\Models\TOS\Deployment;
use App\Models\TOS\Operation;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'ruleset' => 'tos_fields_of_glory']);
    $this->round = TournamentRound::factory()->for($this->tournament)->create();
    $this->op = Operation::factory()->create(['name' => 'Deep Recovery']);
    $this->dep = Deployment::factory()->forSuit('R')->create(['name' => 'Confrontation']);
});

it('stores an operation and deployment on the round scenario', function () {
    $this->actingAs($this->owner)
        ->putJson(route('tournaments.rounds.update', [$this->tournament, $this->round]), [
            'scenario' => ['operation_id' => $this->op->id, 'deployment_id' => $this->dep->id],
        ])->assertOk();

    expect($this->round->fresh()->scenario)->toBe(['operation_id' => $this->op->id, 'deployment_id' => $this->dep->id]);
});

it('merges partial scenario updates instead of wiping the other component', function () {
    $this->round->update(['scenario' => ['operation_id' => $this->op->id]]);

    $this->actingAs($this->owner)
        ->putJson(route('tournaments.rounds.update', [$this->tournament, $this->round]), ['scenario' => ['deployment_id' => $this->dep->id]])
        ->assertOk();

    expect($this->round->fresh()->scenario)->toBe(['operation_id' => $this->op->id, 'deployment_id' => $this->dep->id]);
});

it('rejects an unknown operation or deployment id', function () {
    $this->actingAs($this->owner)
        ->putJson(route('tournaments.rounds.update', [$this->tournament, $this->round]), ['scenario' => ['operation_id' => 9999, 'deployment_id' => 9999]])
        ->assertStatus(422)->assertJsonValidationErrors(['scenario.operation_id', 'scenario.deployment_id']);
});

it('randomizes a TOS scenario from the reference data', function () {
    $this->actingAs($this->owner)
        ->postJson(route('tournaments.rounds.randomize', [$this->tournament, $this->round]))->assertOk();

    expect($this->round->fresh()->scenario)->toBe(['operation_id' => $this->op->id, 'deployment_id' => $this->dep->id]);
    expect($this->round->fresh()->strategy_id)->toBeNull();
});

it('does not spawn tracker games when a Fields of Glory round starts', function () {
    $this->round->update(['scenario' => ['operation_id' => $this->op->id, 'deployment_id' => $this->dep->id]]);
    [$a, $b] = TournamentPlayer::factory()->for($this->tournament)->count(2)->create(['user_id' => User::factory()])->all();
    \App\Models\TournamentGame::factory()->for($this->round, 'round')->create(['player_one_id' => $a->id, 'player_two_id' => $b->id]);

    $this->actingAs($this->owner)
        ->putJson(route('tournaments.rounds.update', [$this->tournament, $this->round]), ['status' => 'in_progress'])
        ->assertOk();

    expect(\App\Models\Game::count())->toBe(0);
    expect($this->round->fresh()->status->value)->toBe('in_progress');
});

it('exposes the resolved scenario on the public view and the options on manage', function () {
    $this->round->update(['scenario' => ['operation_id' => $this->op->id, 'deployment_id' => $this->dep->id]]);

    $this->get(route('tournaments.view', $this->tournament))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rounds.0.tos_scenario.operation.name', 'Deep Recovery')
            ->where('rounds.0.tos_scenario.deployment.suit', 'R'));

    $this->actingAs($this->owner)->get(route('tournaments.manage', $this->tournament))
        ->assertInertia(fn (Assert $page) => $page->has('tos_operations', 1)->has('tos_deployments', 1));
});

it('does not load TOS reference options for Gaining Grounds tournaments', function () {
    $gg = Tournament::factory()->active()->create(['creator_id' => $this->owner->id]);

    $this->actingAs($this->owner)->get(route('tournaments.manage', $gg))
        ->assertInertia(fn (Assert $page) => $page->has('tos_operations', 0)->has('tos_deployments', 0));
});
