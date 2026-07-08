<?php

use App\Enums\GameStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\User;
use App\Services\AchievementService;

beforeEach(function () {
    $this->service = app(AchievementService::class);
});

it('tallies zero games for a user with no completed games', function () {
    $user = User::factory()->create();

    expect($this->service->gameRecordForUser($user->id))->toBe(['total_games' => 0, 'wins' => 0]);
});

it('tallies wins from completed duel games', function () {
    $user = User::factory()->create();

    $won = Game::factory()->create(['status' => GameStatusEnum::Completed, 'is_solo' => false, 'winner_id' => $user->id, 'is_tie' => false]);
    GamePlayer::factory()->create(['game_id' => $won->id, 'user_id' => $user->id, 'slot' => 1]);

    $lost = Game::factory()->create(['status' => GameStatusEnum::Completed, 'is_solo' => false, 'winner_id' => null, 'is_tie' => false]);
    GamePlayer::factory()->create(['game_id' => $lost->id, 'user_id' => $user->id, 'slot' => 1]);

    // In-progress games shouldn't count at all.
    $active = Game::factory()->create(['status' => GameStatusEnum::InProgress]);
    GamePlayer::factory()->create(['game_id' => $active->id, 'user_id' => $user->id, 'slot' => 1]);

    expect($this->service->gameRecordForUser($user->id))->toBe(['total_games' => 2, 'wins' => 1]);
});

it('returns zero tournaments played and a null best finish for a user with no entries', function () {
    $user = User::factory()->create();

    expect($this->service->tournamentRecordForUser($user->id))->toBe(['played' => 0, 'best_finish' => null]);
});

it('counts completed tournament entries', function () {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::Completed]);
    TournamentPlayer::factory()->create(['tournament_id' => $tournament->id, 'user_id' => $user->id]);

    // A non-completed tournament entry shouldn't count.
    $active = Tournament::factory()->create(['status' => TournamentStatusEnum::Active]);
    TournamentPlayer::factory()->create(['tournament_id' => $active->id, 'user_id' => $user->id]);

    $record = $this->service->tournamentRecordForUser($user->id);
    expect($record['played'])->toBe(1);
});

it('awards no badges for a user with zero of everything', function () {
    expect($this->service->computeBadges(0, 0, 0, 0, 0, 0, null, 0))->toBe([]);
});

it('awards the first-blood and collector badges at the first milestone', function () {
    $badges = $this->service->computeBadges(1, 0, 1, 0, 0, 0, null, 0);

    $labels = collect($badges)->pluck('label');
    expect($labels)->toContain('First Blood')
        ->and($labels)->toContain('Collector')
        ->and($labels)->not->toContain('Veteran');
});

it('awards the tournament champion badge only for a first-place finish', function () {
    $secondPlace = $this->service->computeBadges(0, 0, 0, 0, 0, 1, 2, 0);
    expect(collect($secondPlace)->pluck('label'))->not->toContain('Tournament Champion')
        ->and(collect($secondPlace)->pluck('label'))->toContain('Podium Finish');

    $firstPlace = $this->service->computeBadges(0, 0, 0, 0, 0, 1, 1, 0);
    expect(collect($firstPlace)->pluck('label'))->toContain('Tournament Champion');
});

it('awards the fully painted badge only when every owned model is painted', function () {
    $partial = $this->service->computeBadges(0, 0, 10, 0, 5, 0, null, 0);
    expect(collect($partial)->pluck('label'))->not->toContain('Fully Painted');

    $full = $this->service->computeBadges(0, 0, 10, 0, 10, 0, null, 0);
    expect(collect($full)->pluck('label'))->toContain('Fully Painted');
});
