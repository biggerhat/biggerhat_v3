<?php

use App\Enums\GameFormatEnum;
use App\Enums\GamePlayModeEnum;
use App\Enums\GameStatusEnum;
use App\Models\Game;
use App\Support\Game\GameSetupFlow;
use Tests\TestCase;

uses(TestCase::class);

function flowGame(GameFormatEnum $format = GameFormatEnum::Standard, GameStatusEnum $status = GameStatusEnum::Setup): Game
{
    return new Game(['format' => $format, 'status' => $status]);
}

it('walks a standard game through faction, master, crew and scheme, then play', function () {
    $game = flowGame();
    $order = [GameStatusEnum::Setup, GameStatusEnum::FactionSelect, GameStatusEnum::MasterSelect, GameStatusEnum::CrewSelect, GameStatusEnum::SchemeSelect, GameStatusEnum::InProgress];

    foreach (array_slice($order, 0, -1) as $i => $from) {
        expect(GameSetupFlow::next($game, $from))->toBe($order[$i + 1]);
    }
});

it('takes Bonanza Brawl from master straight to play', function () {
    $game = flowGame(GameFormatEnum::BonanzaBrawl);

    expect(GameSetupFlow::next($game, GameStatusEnum::MasterSelect))->toBe(GameStatusEnum::InProgress)
        ->and(GameSetupFlow::statuses($game))->not->toContain(GameStatusEnum::CrewSelect, GameStatusEnum::SchemeSelect);
});

it('treats Campaign like a standard game', function () {
    expect(GameSetupFlow::next(flowGame(GameFormatEnum::Campaign), GameStatusEnum::MasterSelect))->toBe(GameStatusEnum::CrewSelect);
});

it('has no next status for a status that is not a setup step', function () {
    $game = flowGame();

    expect(GameSetupFlow::next($game, GameStatusEnum::InProgress))->toBeNull()
        ->and(GameSetupFlow::next($game, GameStatusEnum::Completed))->toBeNull();
});

it('starts a solo game on faction select and a duel waiting for its opponent', function () {
    expect(GameSetupFlow::startingStatus(true))->toBe(GameStatusEnum::FactionSelect)
        ->and(GameSetupFlow::startingStatus(false))->toBe(GameStatusEnum::Setup);
});

it('lists the steps the screen shows with the status of each', function () {
    $steps = GameSetupFlow::steps(flowGame());

    expect(array_column($steps, 'key'))->toBe(['faction', 'master', 'crew', 'scheme'])
        ->and(array_column($steps, 'status'))->toBe(['faction_select', 'master_select', 'crew_select', 'scheme_select']);
});

it('places the crews between crew and scheme for a tabletop game only', function () {
    $tabletop = new Game(['format' => GameFormatEnum::Standard, 'status' => GameStatusEnum::CrewSelect, 'play_mode' => GamePlayModeEnum::Tabletop]);
    $inPerson = new Game(['format' => GameFormatEnum::Standard, 'status' => GameStatusEnum::CrewSelect, 'play_mode' => GamePlayModeEnum::InPerson]);

    expect(GameSetupFlow::next($tabletop, GameStatusEnum::CrewSelect))->toBe(GameStatusEnum::Deployment)
        ->and(GameSetupFlow::next($tabletop, GameStatusEnum::Deployment))->toBe(GameStatusEnum::SchemeSelect)
        ->and(array_column(GameSetupFlow::steps($tabletop), 'key'))->toBe(['faction', 'master', 'crew', 'deploy', 'scheme'])
        ->and(GameSetupFlow::next($inPerson, GameStatusEnum::CrewSelect))->toBe(GameStatusEnum::SchemeSelect)
        ->and(GameSetupFlow::next($inPerson, GameStatusEnum::Deployment))->toBeNull()
        ->and(array_column(GameSetupFlow::steps($inPerson), 'key'))->toBe(['faction', 'master', 'crew', 'scheme']);
});

it('has no Deployment step for Bonanza Brawl even on the tabletop', function () {
    $game = new Game(['format' => GameFormatEnum::BonanzaBrawl, 'play_mode' => GamePlayModeEnum::Tabletop]);

    expect(GameSetupFlow::statuses($game))->not->toContain(GameStatusEnum::Deployment);
});
