<?php

use App\Tournaments\Rulesets\RulesetRegistry;
use App\Tournaments\ScoringConfig;
use App\Tournaments\ScoringEngine;

function engineFor(string $key): ScoringEngine
{
    return new ScoringEngine(app(RulesetRegistry::class)->get($key)->scoring);
}

describe('Gaining Grounds', function () {
    it('scores 3/1/0 TP with uncapped DIFF', function () {
        $e = engineFor('malifaux_gaining_grounds');

        expect($e->game(11, 2))->toMatchArray(['tp' => 3, 'diff' => 9, 'vp' => 11, 'outcome' => 'win']);
        expect($e->game(5, 5))->toMatchArray(['tp' => 1, 'diff' => 0, 'outcome' => 'tie']);
        expect($e->game(2, 11))->toMatchArray(['tp' => 0, 'diff' => -9, 'outcome' => 'loss']);
    });

    it('uses 3 TP / +4 / 6 VP for a bye and an 11 VP forfeit swing', function () {
        $e = engineFor('malifaux_gaining_grounds');

        expect($e->bye())->toBe(['tp' => 3, 'diff' => 4, 'vp' => 6]);
        expect($e->forfeitWin())->toBe(['tp' => 3, 'diff' => 11, 'vp' => 11]);
        expect($e->forfeitLoss())->toBe(['tp' => 0, 'diff' => -11, 'vp' => 0]);
    });
});

describe('Fields of Glory', function () {
    it('matches the worked example from the packet: 8-4 win is 16 TP / +4 DIFF / 8 VP', function () {
        $e = engineFor('tos_fields_of_glory');

        expect($e->game(8, 4))->toMatchArray(['tp' => 16, 'diff' => 4, 'vp' => 8]);
        // The loser: 10 base TP - 4 DIFF, no win bonus.
        expect($e->game(4, 8))->toMatchArray(['tp' => 6, 'diff' => -4, 'vp' => 4]);
    });

    it('awards 10 TP for a tie and caps DIFF at +/-8', function () {
        $e = engineFor('tos_fields_of_glory');

        expect($e->game(6, 6)['tp'])->toBe(10);
        expect($e->game(15, 1))->toMatchArray(['tp' => 20, 'diff' => 8]);
        expect($e->game(1, 15))->toMatchArray(['tp' => 2, 'diff' => -8]);
    });

    it('uses 19 TP / +7 / 7 VP for a bye and 20 vs 0 for a forfeit', function () {
        $e = engineFor('tos_fields_of_glory');

        expect($e->bye())->toBe(['tp' => 19, 'diff' => 7, 'vp' => 7]);
        expect($e->forfeitWin())->toBe(['tp' => 20, 'diff' => 8, 'vp' => 8]);
        expect($e->forfeitLoss())->toBe(['tp' => 0, 'diff' => -8, 'vp' => 0]);
    });
});

it('lets overrides layer over a config, ignoring nulls but honouring a null diff_cap', function () {
    $base = ScoringConfig::fromArray(['diff_cap' => 8, 'bye_tp' => 19]);

    expect($base->with(['bye_tp' => null])->byeTp)->toBe(19);
    expect($base->with(['bye_tp' => 5])->byeTp)->toBe(5);
    expect($base->with(['diff_cap' => null])->diffCap)->toBeNull();
    expect($base->with(['unknown_key' => 1])->toArray())->toBe($base->toArray());
});

it('suggests rounds by field size', function () {
    $ruleset = app(RulesetRegistry::class)->get('malifaux_gaining_grounds');

    expect($ruleset->suggestedRoundsFor(10))->toBe(3);
    expect($ruleset->suggestedRoundsFor(16))->toBe(4);
    expect($ruleset->suggestedRoundsFor(33))->toBe(5);
    expect($ruleset->suggestedRoundsFor(2))->toBeNull();
});

it('falls back to the default ruleset for unknown keys', function () {
    $registry = app(RulesetRegistry::class);

    expect($registry->resolve('does_not_exist')->key)->toBe('malifaux_gaining_grounds');
    expect($registry->resolve(null)->key)->toBe('malifaux_gaining_grounds');
});
