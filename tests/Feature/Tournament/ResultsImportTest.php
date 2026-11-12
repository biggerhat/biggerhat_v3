<?php

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentStandingsService;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->csv = fn (string $body) => UploadedFile::fake()->createWithContent('results.csv', $body);
    $this->meta = fn (string $body, array $extra = []) => [
        'ruleset' => 'malifaux_gaining_grounds',
        'name' => 'Imported Open',
        'event_date' => now()->subWeek()->toDateString(),
        'encounter_size' => 50,
        'file' => ($this->csv)($body),
        ...$extra,
    ];
    $this->good = "round,table,player_one,player_one_faction,player_one_vp,player_two,player_two_faction,player_two_vp,result,is_bye,is_forfeit\n"
        ."1,1,Alice,Arcanists,9,Bob,Guild,4,completed,false,false\n"
        ."1,2,Carol,Bayou,6,Dave,Neverborn,6,agreed,false,false\n"
        ."2,1,Alice,Arcanists,7,Carol,Bayou,5,completed,false,false\n"
        ."2,2,Bob,Guild,3,Dave,Neverborn,8,completed,false,false\n";
});

it('previews a good file without saving anything', function () {
    $res = $this->actingAs($this->user)->postJson(route('tournaments.import.preview'), ($this->meta)($this->good))->assertOk();

    expect($res->json())->toMatchArray(['ok' => true, 'rounds' => 2, 'games' => 4, 'player_count' => 4, 'errors' => []])
        ->and(Tournament::count())->toBe(0);
});

it('creates a completed tournament with players, rounds, games and working standings', function () {
    $res = $this->actingAs($this->user)->post(route('tournaments.import.store'), ($this->meta)($this->good));

    $t = Tournament::firstOrFail();
    $res->assertRedirect(route('tournaments.view', $t));
    expect($t->status)->toBe(TournamentStatusEnum::Completed)->and($t->creator_id)->toBe($this->user->id)->and($t->planned_rounds)->toBe(2)
        ->and($t->players()->count())->toBe(4)->and($t->rounds()->count())->toBe(2)->and($t->isOrganizer($this->user->id))->toBeTrue();

    $standings = collect(app(TournamentStandingsService::class)->compute($t->fresh()));
    $alice = $standings->firstWhere('display_name', 'Alice');
    expect($alice['wins'])->toBe(2)->and($alice['rank'])->toBe(1)->and($alice['faction'])->toBe('arcanists');
    $tie = $t->rounds()->first()->games()->where('table_number', 2)->first();
    expect($tie->result->value)->toBe('agreed')->and($tie->player_one_vp)->toBe(6);
});

it('records byes and forfeits', function () {
    $body = "round,player_one,player_two,player_one_vp,player_two_vp,is_bye,is_forfeit\n"
        ."1,Alice,Bob,9,4,false,false\n"
        ."1,Carol,,,,true,false\n"
        ."2,Alice,Carol,,,false,true\n"
        ."2,Bob,,,,true,false\n";
    $this->actingAs($this->user)->post(route('tournaments.import.store'), ($this->meta)($body))->assertSessionHasNoErrors();

    $t = Tournament::firstOrFail();
    $games = $t->rounds()->with('games')->get()->flatMap->games;
    expect($games->where('is_bye', true))->toHaveCount(2)
        ->and($games->where('is_forfeit', true)->first()->forfeit_player_id)->toBe($t->players()->where('display_name', 'Carol')->value('id'));
});

it('reads score components for rulesets that split the score', function () {
    $body = "round,player_one,player_two,player_one_strategy_vp,player_one_scheme_vp,player_two_strategy_vp,player_two_scheme_vp\n1,Alice,Bob,4,3,2,1\n";
    $this->actingAs($this->user)->post(route('tournaments.import.store'), ($this->meta)($body))->assertSessionHasNoErrors();

    $game = Tournament::firstOrFail()->rounds()->first()->games()->first();
    expect($game->player_one_vp)->toBe(7)->and($game->player_two_vp)->toBe(3)->and($game->score_breakdown['player_one'])->toBe(['strategy' => 4, 'scheme' => 3]);
});

it('reports every kind of problem with a line number and creates nothing', function (string $body, string $expected) {
    $res = $this->actingAs($this->user)->postJson(route('tournaments.import.preview'), ($this->meta)($body))->assertOk();

    expect($res->json('ok'))->toBeFalse()->and(implode(' ', $res->json('errors')))->toContain($expected);
    $this->actingAs($this->user)->post(route('tournaments.import.store'), ($this->meta)($body))->assertSessionHasErrors('file');
    expect(Tournament::count())->toBe(0);
})->with([
    'missing columns' => ["round,player_one\n1,Alice\n", 'Missing the "player_two" column'],
    'bad round' => ["round,player_one,player_two,player_one_vp,player_two_vp\nx,Alice,Bob,1,1\n", 'Line 2: the round'],
    'one player missing' => ["round,player_one,player_two,player_one_vp,player_two_vp\n1,Alice,,1,1\n", 'both players are needed'],
    'plays themself' => ["round,player_one,player_two,player_one_vp,player_two_vp\n1,Alice,alice,1,1\n", 'cannot play themself'],
    'score not a number' => ["round,player_one,player_two,player_one_vp,player_two_vp\n1,Alice,Bob,a,1\n", 'player_one_vp must be a whole number'],
    'score too high' => ["round,player_one,player_two,player_one_vp,player_two_vp\n1,Alice,Bob,50,1\n", 'more than this format allows'],
    'paired twice' => ["round,player_one,player_two,player_one_vp,player_two_vp\n1,Alice,Bob,1,1\n1,Carol,alice,1,1\n", 'already paired in round 1'],
    'round gap' => ["round,player_one,player_two,player_one_vp,player_two_vp\n1,Alice,Bob,1,1\n3,Alice,Bob,1,1\n", 'without gaps'],
    'empty' => ["round,player_one,player_two,player_one_vp,player_two_vp\n", 'no games'],
    'no score columns' => ["round,player_one,player_two\n1,Alice,Bob\n", 'Missing the score columns'],
]);

it('warns about unknown factions but still imports', function () {
    $body = "round,player_one,player_one_faction,player_two,player_two_faction,player_one_vp,player_two_vp\n1,Alice,Hobbits,Bob,Guild,5,4\n";
    $res = $this->actingAs($this->user)->postJson(route('tournaments.import.preview'), ($this->meta)($body))->assertOk();

    expect($res->json('ok'))->toBeTrue()->and($res->json('warnings.0'))->toContain('Hobbits');
});

it('validates the form fields', function () {
    $url = route('tournaments.import.preview');

    $this->actingAs($this->user)->postJson($url, ($this->meta)($this->good, ['name' => '']))->assertJsonValidationErrors('name');
    $this->actingAs($this->user)->postJson($url, ($this->meta)($this->good, ['event_date' => now()->addDay()->toDateString()]))->assertJsonValidationErrors('event_date');
    $this->actingAs($this->user)->postJson($url, ($this->meta)($this->good, ['ruleset' => 'nope']))->assertJsonValidationErrors('ruleset');
    $this->actingAs($this->user)->postJson($url, ($this->meta)($this->good, ['encounter_size' => 5]))->assertJsonValidationErrors('encounter_size');
    $this->actingAs($this->user)->postJson($url, ['ruleset' => 'malifaux_gaining_grounds', 'name' => 'x', 'event_date' => now()->toDateString(), 'encounter_size' => 50])->assertJsonValidationErrors('file');
});

it('needs an account', function () {
    $this->postJson(route('tournaments.import.preview'), ($this->meta)($this->good))->assertUnauthorized();
    $this->get(route('tournaments.import.create'))->assertRedirect();
});

it('serves the import page and a template for the format', function () {
    $this->actingAs($this->user)->get(route('tournaments.import.create'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Tournaments/Import')->has('rulesets')->has('seasons'));

    $csv = $this->actingAs($this->user)->get(route('tournaments.import.template', 'malifaux_gaining_grounds'))->assertOk()->streamedContent();
    expect($csv)->toContain('player_one_strategy_vp')->toContain('Alice');
});

it('imports a file that was exported from another event (round trip)', function () {
    $source = Tournament::factory()->active()->create(['creator_id' => $this->user->id]);
    $a = App\Models\TournamentPlayer::factory()->for($source)->create(['display_name' => 'Zed', 'faction' => 'guild']);
    $b = App\Models\TournamentPlayer::factory()->for($source)->create(['display_name' => 'Yan', 'faction' => 'bayou']);
    $round = App\Models\TournamentRound::factory()->for($source)->create(['round_number' => 1, 'status' => 'completed']);
    App\Models\TournamentGame::factory()->for($round, 'round')->create(['player_one_id' => $a->id, 'player_two_id' => $b->id, 'table_number' => 1, 'result' => 'completed',
        'player_one_vp' => 7, 'player_two_vp' => 3, 'score_breakdown' => ['player_one' => ['strategy' => 4, 'scheme' => 3], 'player_two' => ['strategy' => 2, 'scheme' => 1]]]);
    $csv = $this->actingAs($this->user)->get(route('tournaments.export_csv', $source))->assertOk()->streamedContent();

    $this->actingAs($this->user)->post(route('tournaments.import.store'), ($this->meta)($csv))->assertSessionHasNoErrors();

    $copy = Tournament::where('name', 'Imported Open')->firstOrFail();
    $game = $copy->rounds()->first()->games()->first();
    expect($game->player_one_vp)->toBe(7)->and($game->score_breakdown['player_two'])->toBe(['strategy' => 2, 'scheme' => 1])
        ->and($copy->players()->pluck('display_name')->sort()->values()->all())->toBe(['Yan', 'Zed']);
});
