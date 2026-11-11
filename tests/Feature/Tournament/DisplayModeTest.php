<?php

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use Inertia\Testing\AssertableInertia as Assert;

it('serves a public display page with the current round, standings and ruleset', function () {
    $t = Tournament::factory()->active()->create();
    $done = TournamentRound::factory()->for($t)->completed()->create(['round_number' => 1]);
    $live = TournamentRound::factory()->for($t)->inProgress()->create(['round_number' => 2]);
    [$a, $b] = TournamentPlayer::factory()->for($t)->count(2)->create()->all();
    TournamentGame::factory()->for($live, 'round')->create(['player_one_id' => $a->id, 'player_two_id' => $b->id, 'table_number' => 3]);

    $this->get(route('tournaments.display', $t))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tournaments/Display')
            ->where('tournament.uuid', $t->uuid)
            ->has('rounds', 2)
            ->where('rounds.1.status', 'in_progress')
            ->where('rounds.1.games.0.table_number', 3)
            ->has('standings', 2)
            ->where('ruleset.key', 'malifaux_gaining_grounds')
            ->missing('scheme_stats'));
});

it('displays a Fields of Glory event with its operation and deployment', function () {
    $t = Tournament::factory()->active()->create(['ruleset' => 'tos_fields_of_glory']);
    $op = \App\Models\TOS\Operation::factory()->create(['name' => 'Deep Recovery']);
    $dep = \App\Models\TOS\Deployment::factory()->create(['name' => 'Corners']);
    TournamentRound::factory()->for($t)->create(['scenario' => ['operation_id' => $op->id, 'deployment_id' => $dep->id]]);

    $this->get(route('tournaments.display', $t))->assertInertia(fn (Assert $page) => $page
        ->where('rounds.0.tos_scenario.operation.name', 'Deep Recovery')
        ->where('rounds.0.tos_scenario.deployment.name', 'Corners'));
});

it('still serves the normal view page with the shared props after the split', function () {
    $t = Tournament::factory()->active()->create();

    $this->get(route('tournaments.view', $t))->assertInertia(fn (Assert $page) => $page
        ->component('Tournaments/View')->has('scheme_stats')->has('allegiances')->has('factions'));
});
