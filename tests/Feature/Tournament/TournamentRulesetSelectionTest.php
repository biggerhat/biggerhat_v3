<?php

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->organizer = User::factory()->create();
    $this->payload = fn (array $extra = []) => [
        'name' => 'Test Event',
        'encounter_size' => 50,
        'planned_rounds' => 3,
        'season' => Tournament::factory()->make()->season->value,
        'event_date' => now()->addWeek()->toDateString(),
        ...$extra,
    ];
});

it('defaults rows inserted without a ruleset (legacy data) to Malifaux Gaining Grounds', function () {
    $owner = User::factory()->create();
    $attrs = Tournament::factory()->make(['creator_id' => $owner->id, 'uuid' => (string) Str::uuid()])->getAttributes();
    unset($attrs['ruleset'], $attrs['scoring_config']);
    $id = DB::table('tournaments')->insertGetId([...$attrs, 'season' => $attrs['season'] instanceof BackedEnum ? $attrs['season']->value : $attrs['season'], 'created_at' => now(), 'updated_at' => now()]);

    $t = Tournament::find($id);
    expect($t->ruleset)->toBe('malifaux_gaining_grounds');
    expect($t->rulesetDefinition()->key)->toBe('malifaux_gaining_grounds');
    expect($t->ruleset_label)->toBe('Malifaux — Gaining Grounds');
    expect($t->scoringConfig()->byeTp)->toBe(3);
});

it('treats a missing or unknown stored ruleset as Gaining Grounds', function () {
    $t = Tournament::factory()->create();
    DB::table('tournaments')->where('id', $t->id)->update(['ruleset' => 'removed_from_config']);

    expect(Tournament::find($t->id)->rulesetDefinition()->key)->toBe('malifaux_gaining_grounds');
});

it('creates a Gaining Grounds tournament when no ruleset is sent', function () {
    $this->actingAs($this->organizer)->post(route('tournaments.store'), ($this->payload)())->assertRedirect();

    $t = Tournament::latest('id')->first();
    expect($t->ruleset)->toBe('malifaux_gaining_grounds');
    expect($t->scoringConfig()->byeTp)->toBe(3);
});

it('creates a Fields of Glory tournament with that ruleset’s scoring', function () {
    $this->actingAs($this->organizer)
        ->post(route('tournaments.store'), ($this->payload)(['ruleset' => 'tos_fields_of_glory', 'bye_tp' => null, 'bye_diff' => null, 'bye_vp' => null]))
        ->assertRedirect();

    $t = Tournament::latest('id')->first();
    expect($t->ruleset)->toBe('tos_fields_of_glory');
    expect($t->bye_tp)->toBeNull();
    expect($t->scoringConfig())->byeTp->toBe(19)->byeDiff->toBe(7)->diffCap->toBe(8);
    expect($t->tiebreakerKeys())->toBe(['sos']);
});

it('rejects an unknown ruleset', function () {
    $this->actingAs($this->organizer)
        ->post(route('tournaments.store'), ($this->payload)(['ruleset' => 'nope']))
        ->assertSessionHasErrors('ruleset');
});

it('exposes rulesets and their defaults on the create page', function () {
    $this->actingAs($this->organizer)
        ->get(route('tournaments.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('default_ruleset', 'malifaux_gaining_grounds')
            ->has('rulesets', 3)
            ->where('rulesets.1.key', 'tos_fields_of_glory')
            ->where('rulesets.1.scoring.bye_tp', 19));
});

it('carries the ruleset when copying an event', function () {
    $source = Tournament::factory()->create(['creator_id' => $this->organizer->id, 'ruleset' => 'tos_fields_of_glory']);

    $this->actingAs($this->organizer)
        ->get(route('tournaments.create', ['copy' => $source->uuid]))
        ->assertInertia(fn (Assert $page) => $page->where('template.ruleset', 'tos_fields_of_glory'));
});

it('passes the effective ruleset scoring to the manage page', function () {
    $t = Tournament::factory()->create([
        'creator_id' => $this->organizer->id,
        'ruleset' => 'tos_fields_of_glory',
        'bye_tp' => 15,
    ]);

    $this->actingAs($this->organizer)
        ->get(route('tournaments.manage', $t))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ruleset.key', 'tos_fields_of_glory')
            ->where('ruleset.scoring.bye_tp', 15)
            ->where('ruleset.default_scoring.bye_tp', 19)
            ->where('ruleset.tiebreakers', ['sos']));
});

it('lets a bye override be cleared back to the ruleset value', function () {
    $t = Tournament::factory()->create(['creator_id' => $this->organizer->id, 'bye_tp' => 5]);

    $this->actingAs($this->organizer)
        ->putJson(route('tournaments.update', $t), ['bye_tp' => null])
        ->assertSuccessful();

    expect($t->fresh()->bye_tp)->toBeNull();
    expect($t->fresh()->scoringConfig()->byeTp)->toBe(3);
});

it('sends each format’s round-clock rules to the pages', function () {
    $gg = Tournament::factory()->create(['creator_id' => $this->organizer->id]);
    $fog = Tournament::factory()->create(['creator_id' => $this->organizer->id, 'ruleset' => 'tos_fields_of_glory']);

    $this->actingAs($this->organizer)->get(route('tournaments.manage', $gg))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ruleset.time_rules.last_turn_minutes', 15)
            ->where('ruleset.time_rules.time_called', fn ($v) => str_contains((string) $v, 'one additional') || str_contains((string) $v, 'plus one more')));
    $this->actingAs($this->organizer)->get(route('tournaments.manage', $fog))
        ->assertInertia(fn (Assert $page) => $page->where('ruleset.time_rules.time_called', null)->where('ruleset.time_rules.last_turn', fn ($v) => str_contains((string) $v, 'do not start a new one')));
});
