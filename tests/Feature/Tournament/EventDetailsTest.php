<?php

use App\Models\Tournament;
use App\Models\TournamentTable;
use App\Models\User;
use App\Tournaments\RichText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->owner = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->owner->id]);
    $this->doc = fn (array $content) => ['type' => 'doc', 'content' => $content];
    $this->para = fn (string $text, array $marks = []) => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text] + ($marks ? ['marks' => $marks] : [])]];
});

it('stores the details document and shows it on the public page', function () {
    $doc = ($this->doc)([['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Venue']]], ($this->para)('Doors at 9, bring your own terrain.')]);

    $this->actingAs($this->owner)->putJson(route('tournaments.details', $this->tournament), ['details' => $doc])->assertOk();

    expect($this->tournament->fresh()->details['content'][0]['type'])->toBe('heading');
    $this->get(route('tournaments.view', $this->tournament))->assertInertia(fn (Assert $page) => $page->where('tournament.details.content.1.content.0.text', 'Doors at 9, bring your own terrain.'));
});

it('clears the details with null or an empty document', function () {
    $this->tournament->update(['details' => ($this->doc)([($this->para)('Old')])]);

    $this->actingAs($this->owner)->putJson(route('tournaments.details', $this->tournament), ['details' => ($this->doc)([['type' => 'paragraph']])])->assertOk();

    expect($this->tournament->fresh()->details)->toBeNull();
});

it('lets only organizers write the details', function () {
    $this->putJson(route('tournaments.details', $this->tournament), ['details' => null])->assertUnauthorized();
    $this->actingAs(User::factory()->create())->putJson(route('tournaments.details', $this->tournament), ['details' => null])->assertForbidden();
});

it('rejects an oversized document', function () {
    $big = ($this->doc)([($this->para)(str_repeat('x', 110_000))]);

    $this->actingAs($this->owner)->putJson(route('tournaments.details', $this->tournament), ['details' => $big])->assertJsonValidationErrors('details');
});

it('keeps only allow-listed nodes, marks, links and images', function () {
    $dirty = ($this->doc)([
        ['type' => 'script', 'content' => [['type' => 'text', 'text' => 'alert(1)']]],
        ($this->para)('safe', [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']], ['type' => 'bold'], ['type' => 'onclick']]),
        ($this->para)('ok', [['type' => 'link', 'attrs' => ['href' => 'https://example.com/rules']]]),
        ['type' => 'image', 'attrs' => ['src' => 'https://evil.test/x.png']],
        ['type' => 'image', 'attrs' => ['src' => '/storage/tournaments/../../.env']],
        ['type' => 'image', 'attrs' => ['src' => '/storage/tournaments/1/content/a.png', 'alt' => 'Map']],
    ]);

    $clean = RichText::sanitize($dirty);

    expect($clean['content'])->toHaveCount(3)
        ->and($clean['content'][0]['content'][0]['marks'])->toBe([['type' => 'bold']])
        ->and($clean['content'][1]['content'][0]['marks'][0]['attrs']['href'])->toBe('https://example.com/rules')
        ->and($clean['content'][2]['attrs']['src'])->toBe('/storage/tournaments/1/content/a.png');
});

it('treats a non-document as empty', function () {
    expect(RichText::sanitize(null))->toBeNull()
        ->and(RichText::sanitize(['type' => 'paragraph']))->toBeNull();
});

it('uploads an image for the details and refuses non-images and strangers', function () {
    $url = route('tournaments.details.image', $this->tournament);

    $res = $this->actingAs($this->owner)->post($url, ['image' => UploadedFile::fake()->image('map.png')])->assertOk();
    expect($res->json('url'))->toStartWith("/storage/tournaments/{$this->tournament->id}/content/");
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $res->json('url')));
    expect(RichText::isOwnImage($res->json('url')))->toBeTrue();

    $this->actingAs($this->owner)->postJson($url, ['image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertJsonValidationErrors('image');
    $this->actingAs(User::factory()->create())->postJson($url, ['image' => UploadedFile::fake()->image('map.png')])->assertForbidden();
});

it('saves a table description with a photo, replaces it and removes it', function () {
    $url = route('tournaments.tables.save', [$this->tournament, 3]);

    $res = $this->actingAs($this->owner)->post($url, ['name' => 'The Mill', 'description' => 'Two LoS-blocking buildings', 'image' => UploadedFile::fake()->image('t3.jpg')])->assertOk();
    $first = TournamentTable::where('tournament_id', $this->tournament->id)->where('number', 3)->firstOrFail();
    expect($res->json('table.image_url'))->toStartWith('/storage/tournaments/')->and($first->name)->toBe('The Mill');
    Storage::disk('public')->assertExists($first->image_path);

    $this->actingAs($this->owner)->post($url, ['name' => 'The Mill', 'image' => UploadedFile::fake()->image('t3b.jpg')])->assertOk();
    Storage::disk('public')->assertMissing($first->image_path);
    expect($first->fresh()->description)->toBeNull();

    $this->actingAs($this->owner)->post($url, ['name' => 'The Mill', 'remove_image' => true])->assertOk();
    expect($first->fresh()->image_path)->toBeNull();

    $this->actingAs($this->owner)->post($url, ['name' => ' '])->assertOk()->assertJsonPath('table', null);
    expect(TournamentTable::count())->toBe(0);
});

it('shows table notes to the public and the organizer', function () {
    TournamentTable::create(['tournament_id' => $this->tournament->id, 'number' => 2, 'name' => 'Ruins', 'description' => 'Lots of cover']);

    $this->get(route('tournaments.view', $this->tournament))->assertInertia(fn (Assert $page) => $page->where('tables.2.name', 'Ruins')->where('tables.2.description', 'Lots of cover'));
    $this->actingAs($this->owner)->get(route('tournaments.manage', $this->tournament))->assertInertia(fn (Assert $page) => $page->where('tables.2.name', 'Ruins'));
});

it('removes a table', function () {
    $table = TournamentTable::create(['tournament_id' => $this->tournament->id, 'number' => 5, 'name' => 'Docks']);

    $this->actingAs($this->owner)->deleteJson(route('tournaments.tables.destroy', [$this->tournament, 5]))->assertOk();

    expect(TournamentTable::find($table->id))->toBeNull();
    $this->actingAs(User::factory()->create())->deleteJson(route('tournaments.tables.destroy', [$this->tournament, 5]))->assertForbidden();
});

it('only offers table details to rulesets that ask for them, and for sane numbers', function () {
    $this->actingAs($this->owner)->post(route('tournaments.tables.save', [$this->tournament, 0]), ['name' => 'x'])->assertNotFound();
    $this->actingAs($this->owner)->post(route('tournaments.tables.save', [$this->tournament, 201]), ['name' => 'x'])->assertNotFound();

    $fog = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'ruleset' => 'tos_fields_of_glory']);
    $this->actingAs($this->owner)->post(route('tournaments.tables.save', [$fog, 1]), ['name' => 'x'])->assertNotFound();
    $this->get(route('tournaments.view', $fog))->assertInertia(fn (Assert $page) => $page->where('tables', []));
});

it('refuses a table photo that is not an image and a stranger saving a table', function () {
    $url = route('tournaments.tables.save', [$this->tournament, 1]);

    $this->actingAs($this->owner)->postJson($url, ['image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertJsonValidationErrors('image');
    $this->actingAs(User::factory()->create())->postJson($url, ['name' => 'Mine'])->assertForbidden();
});
