<?php

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;

function completedTournament(array $attrs = []): Tournament
{
    $t = Tournament::factory()->create(['status' => 'completed', 'name' => 'Spring Showdown', ...$attrs]);
    [$a, $b, $c, $d] = TournamentPlayer::factory()->for($t)->count(4)->sequence(
        ['display_name' => 'Alice', 'faction' => 'arcanists'], ['display_name' => 'Bob', 'faction' => 'guild'],
        ['display_name' => 'Carol', 'faction' => 'bayou'], ['display_name' => 'Dave', 'faction' => 'neverborn'],
    )->create()->all();
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 1, 'status' => 'completed']);
    TournamentGame::factory()->for($round, 'round')->withScore(8, 2)->create(['player_one_id' => $a->id, 'player_two_id' => $b->id, 'result' => 'completed']);
    TournamentGame::factory()->for($round, 'round')->withScore(6, 4)->create(['player_one_id' => $c->id, 'player_two_id' => $d->id, 'result' => 'completed']);

    return $t;
}

it('serves a PDF of the final standings and podium for a completed tournament, to anyone', function () {
    $t = completedTournament();

    $response = $this->get(route('tournaments.results_pdf', $t->uuid))->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and($response->headers->get('content-disposition'))->toContain('spring-showdown-results.pdf');
});

it('renders the sheet with the podium, the standings and the ruleset columns', function () {
    $t = completedTournament(['ruleset' => 'tos_fields_of_glory']);
    $t->load('rounds');

    $html = view('PDF.TournamentResults', [
        'tournament' => $t,
        'rulesetLabel' => $t->rulesetDefinition()->label,
        'standings' => collect(app(App\Services\TournamentStandingsService::class)->compute($t))->map(fn ($r) => $r + ['faction_label' => ucfirst((string) $r['faction'])]),
        'podium' => collect(),
        'tiebreakers' => $t->tiebreakerKeys(),
        'isTeamEvent' => false,
        'organizer' => 'Olivia',
        'url' => 'https://example.test/t',
    ])->render();

    expect($html)->toContain('Spring Showdown')->toContain('Alice')->toContain('Final standings')->toContain('SoS')->not->toContain('>VP<');
});

it('is not available until the tournament is completed', function () {
    foreach (['draft', 'registration', 'active'] as $status) {
        $t = Tournament::factory()->create(['status' => $status]);
        $this->get(route('tournaments.results_pdf', $t->uuid))->assertNotFound();
    }
});

it('handles an event nobody played in', function () {
    $t = Tournament::factory()->create(['status' => 'completed']);

    $this->get(route('tournaments.results_pdf', $t->uuid))->assertOk();
});
