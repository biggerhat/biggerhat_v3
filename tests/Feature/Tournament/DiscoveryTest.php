<?php

use App\Models\Tournament;
use Inertia\Testing\AssertableInertia as Assert;

it('serves a one-event calendar file for a tournament', function () {
    $t = Tournament::factory()->inRegistration()->create([
        'name' => 'Spring, Open; "Showdown"', 'location' => 'Game Hall, Main St', 'event_date' => '2026-11-18', 'description' => "Bring dice.\nRound 1 at ten.",
    ]);

    $response = $this->get(route('tournaments.calendar', $t->uuid))->assertOk();
    $body = $response->getContent();

    expect($response->headers->get('content-type'))->toContain('text/calendar')
        ->and($response->headers->get('content-disposition'))->toContain('.ics')
        ->and($body)->toStartWith("BEGIN:VCALENDAR\r\n")->toEndWith("END:VCALENDAR\r\n")
        ->toContain("UID:{$t->uuid}@")
        ->toContain("DTSTART;VALUE=DATE:20261118\r\n")->toContain("DTEND;VALUE=DATE:20261119\r\n")
        ->toContain('SUMMARY:Spring\\, Open\; "Showdown"')->toContain('LOCATION:Game Hall\\, Main St')
        ->toContain('Bring dice.\\nRound 1 at ten.')->toContain(route('tournaments.view', $t));
});

it('folds long lines at 75 octets and keeps multibyte characters whole', function () {
    $t = Tournament::factory()->create(['name' => str_repeat('Zażółć gęślą jaźń ', 12)]);

    $lines = explode("\r\n", rtrim($this->get(route('tournaments.calendar', $t->uuid))->getContent()));

    foreach ($lines as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75)->and(mb_check_encoding($line, 'UTF-8'))->toBeTrue();
    }
    $unfolded = preg_replace("/\r\n /", '', rtrim($this->get(route('tournaments.calendar', $t->uuid))->getContent()));
    expect($unfolded)->toContain('SUMMARY:'.trim(str_repeat('Zażółć gęślą jaźń ', 12)));
});

it('filters the public list by text, format and upcoming / past', function () {
    Tournament::factory()->inRegistration()->create(['name' => 'Alpine Cup', 'location' => 'Denver', 'ruleset' => 'malifaux_gaining_grounds', 'event_date' => '2026-12-01']);
    Tournament::factory()->active()->create(['name' => 'Bayou Brawl', 'location' => 'New Orleans', 'ruleset' => 'tos_fields_of_glory', 'event_date' => '2026-11-20']);
    Tournament::factory()->create(['name' => 'Old Showdown', 'status' => 'completed', 'location' => 'Denver', 'ruleset' => 'malifaux_gaining_grounds', 'event_date' => '2026-01-10']);
    Tournament::factory()->create(['name' => 'Secret Draft', 'status' => 'draft', 'location' => 'Denver']);

    $names = fn (array $query) => collect(
        $this->get(route('tournaments.index', $query))->assertOk()->viewData('page')['props']['public_tournaments']
    )->pluck('name')->sort()->values()->all();

    expect($names([]))->toBe(['Alpine Cup', 'Bayou Brawl', 'Old Showdown'])           // drafts stay private
        ->and($names(['q' => 'denver']))->toBe(['Alpine Cup', 'Old Showdown'])        // matches the place
        ->and($names(['q' => 'bayou']))->toBe(['Bayou Brawl'])                         // matches the name
        ->and($names(['ruleset' => 'tos_fields_of_glory']))->toBe(['Bayou Brawl'])
        ->and($names(['when' => 'upcoming']))->toBe(['Alpine Cup', 'Bayou Brawl'])
        ->and($names(['when' => 'past']))->toBe(['Old Showdown'])
        ->and($names(['ruleset' => 'not-a-ruleset', 'when' => 'sometime']))->toBe(['Alpine Cup', 'Bayou Brawl', 'Old Showdown']);
});

it('lists upcoming events soonest first and paginates the public list', function () {
    foreach (range(1, 23) as $i) {
        Tournament::factory()->inRegistration()->create(['name' => sprintf('Event %02d', $i), 'event_date' => now()->addDays($i)->toDateString()]);
    }

    $this->get(route('tournaments.index', ['when' => 'upcoming']))->assertInertia(fn (Assert $page) => $page
        ->has('public_tournaments', 20)->where('public_tournaments.0.name', 'Event 01')
        ->where('public_pagination', ['current_page' => 1, 'last_page' => 2, 'total' => 23])
        ->where('filters.when', 'upcoming')->has('ruleset_options'));

    $this->get(route('tournaments.index', ['when' => 'upcoming', 'page' => 2]))->assertInertia(fn (Assert $page) => $page->has('public_tournaments', 3));
});
