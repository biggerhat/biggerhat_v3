<?php

use App\Support\Search;

test('wraps plain input in % wildcards', function () {
    expect(Search::wildcardLike('damage'))->toBe('%damage%');
});

test('translates user * to SQL %', function () {
    expect(Search::wildcardLike('remove * token'))->toBe('%remove % token%');
});

test('escapes literal SQL wildcard characters from user input', function () {
    expect(Search::wildcardLike('100%'))->toBe('%100\\%%');
    expect(Search::wildcardLike('a_b'))->toBe('%a\\_b%');
});

test('escapes backslashes before translating wildcards', function () {
    // A literal backslash from the user must not be allowed to escape our own escapes.
    expect(Search::wildcardLike('a\\b'))->toBe('%a\\\\b%');
});

test('empty input produces a match-all pattern', function () {
    expect(Search::wildcardLike(''))->toBe('%%');
});
