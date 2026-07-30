<?php

use App\Enums\CrewUpgradeRestrictionEnum;
use App\Support\Campaign\CrewCardUpgradeMatcher;

function matcherCall(
    CrewUpgradeRestrictionEnum $restriction,
    array $crewKeywords = ['Ten Thunders'],
    array $modelKeywords = ['Ten Thunders'],
    array $characteristics = [],
    ?string $station = 'minion',
    ?int $size = 2,
    string $acquiredVia = 'hire',
): bool {
    return CrewCardUpgradeMatcher::matches($restriction->value, $crewKeywords, $modelKeywords, $characteristics, $station, $size, $acquiredVia);
}

it('returns false for a null/unrecognized restriction', function () {
    expect(CrewCardUpgradeMatcher::matches(null, ['Ten Thunders'], ['Ten Thunders'], [], 'minion', 2, 'hire'))->toBeFalse();
    expect(CrewCardUpgradeMatcher::matches('not_a_real_restriction', ['Ten Thunders'], ['Ten Thunders'], [], 'minion', 2, 'hire'))->toBeFalse();
});

it('Friendly matches regardless of keyword', function () {
    expect(matcherCall(CrewUpgradeRestrictionEnum::Friendly, crewKeywords: ['A'], modelKeywords: ['B']))->toBeTrue();
});

it('FriendlyKeyword requires a shared keyword only', function () {
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyKeyword))->toBeTrue();
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyKeyword, crewKeywords: ['A'], modelKeywords: ['B']))->toBeFalse();
});

it('FriendlyNonPeonKeyword excludes Peons even in-keyword', function () {
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyNonPeonKeyword, station: 'minion'))->toBeTrue();
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyNonPeonKeyword, station: 'peon'))->toBeFalse();
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyNonPeonKeyword, crewKeywords: ['A'], modelKeywords: ['B'], station: 'minion'))->toBeFalse();
});

it('FriendlyNonPeonBothKeywords (T3-33) requires every one of the crew\'s keywords, not just one', function () {
    expect(matcherCall(
        CrewUpgradeRestrictionEnum::FriendlyNonPeonBothKeywords,
        crewKeywords: ['Ten Thunders', 'Ortega'],
        modelKeywords: ['Ten Thunders', 'Ortega'],
    ))->toBeTrue();
    // Shares only one of the crew's two keywords — the pg 31-32 "either" -> "both" upgrade means this must fail.
    expect(matcherCall(
        CrewUpgradeRestrictionEnum::FriendlyNonPeonBothKeywords,
        crewKeywords: ['Ten Thunders', 'Ortega'],
        modelKeywords: ['Ten Thunders'],
    ))->toBeFalse();
    expect(matcherCall(
        CrewUpgradeRestrictionEnum::FriendlyNonPeonBothKeywords,
        crewKeywords: ['Ten Thunders', 'Ortega'],
        modelKeywords: ['Ten Thunders', 'Ortega'],
        station: 'peon',
    ))->toBeFalse();
});

it('FriendlyUniqueKeyword requires the Unique characteristic', function () {
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyUniqueKeyword, characteristics: ['Unique']))->toBeTrue();
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyUniqueKeyword, characteristics: []))->toBeFalse();
});

it('FriendlyKeywordSize3OrGreater checks the model\'s own Size stat', function () {
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyKeywordSize3OrGreater, size: 3))->toBeTrue();
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyKeywordSize3OrGreater, size: 2))->toBeFalse();
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyKeywordSize3OrGreater, size: null))->toBeFalse();
});

it('FriendlyKeywordWithoutSummonToken excludes a model acquired via Summon', function () {
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyKeywordWithoutSummonToken, acquiredVia: 'hire'))->toBeTrue();
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyKeywordWithoutSummonToken, acquiredVia: 'summon'))->toBeFalse();
});

it('FriendlyKeywordMinion requires the Minion station specifically', function () {
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyKeywordMinion, station: 'minion'))->toBeTrue();
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyKeywordMinion, station: 'enforcer'))->toBeFalse();
});

it('FriendlyUniqueKeywordAndKeywordWithBeastCharacteristic accepts either Unique or Beast, not neither', function () {
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyUniqueKeywordAndKeywordWithBeastCharacteristic, characteristics: ['Unique']))->toBeTrue();
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyUniqueKeywordAndKeywordWithBeastCharacteristic, characteristics: ['Beast']))->toBeTrue();
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyUniqueKeywordAndKeywordWithBeastCharacteristic, characteristics: []))->toBeFalse();
});

it('FriendlyNonGaminKeyword excludes a model that itself carries the Gamin keyword', function () {
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyNonGaminKeyword, modelKeywords: ['Ten Thunders']))->toBeTrue();
    expect(matcherCall(CrewUpgradeRestrictionEnum::FriendlyNonGaminKeyword, modelKeywords: ['Ten Thunders', 'Gamin']))->toBeFalse();
});
