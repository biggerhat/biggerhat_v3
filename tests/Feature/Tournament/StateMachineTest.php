<?php

use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Services\TournamentStateMachine;

beforeEach(function () {
    $this->sm = app(TournamentStateMachine::class);
});

describe('tournament-level transitions', function () {
    it('allows Draft → Registration with no guards', function () {
        $t = Tournament::factory()->create();

        expect($this->sm->canTransitionTournamentTo($t, TournamentStatusEnum::Registration))->toBeNull();
    });

    it('rejects Draft → Active (skipping Registration)', function () {
        $t = Tournament::factory()->create();

        expect($this->sm->canTransitionTournamentTo($t, TournamentStatusEnum::Active))
            ->toBe('Invalid status transition');
    });

    it('blocks Registration → Active when fewer than 2 players', function () {
        $t = Tournament::factory()->inRegistration()->create();
        TournamentPlayer::factory()->for($t)->create();

        expect($this->sm->canTransitionTournamentTo($t, TournamentStatusEnum::Active))
            ->toBe('Need at least 2 players to start');
    });

    it('blocks Registration → Active when a player has no faction', function () {
        $t = Tournament::factory()->inRegistration()->create();
        TournamentPlayer::factory()->for($t)->count(2)->create();
        TournamentPlayer::factory()->for($t)->create(['faction' => null]);

        expect($this->sm->canTransitionTournamentTo($t, TournamentStatusEnum::Active))
            ->toContain('missing faction selection');
    });

    it('allows Registration → Active when guards pass', function () {
        $t = Tournament::factory()->inRegistration()->create();
        TournamentPlayer::factory()->for($t)->count(4)->create();

        expect($this->sm->canTransitionTournamentTo($t, TournamentStatusEnum::Active))->toBeNull();
    });

    it('blocks Active → Completed when no rounds played', function () {
        $t = Tournament::factory()->active()->create();

        expect($this->sm->canTransitionTournamentTo($t, TournamentStatusEnum::Completed))
            ->toBe('No rounds have been played');
    });

    it('blocks Active → Completed when a round is incomplete', function () {
        $t = Tournament::factory()->active()->create();
        TournamentRound::factory()->for($t)->inProgress()->create();

        expect($this->sm->canTransitionTournamentTo($t, TournamentStatusEnum::Completed))
            ->toContain('still in progress');
    });

    it('allows Active → Completed when all rounds are completed', function () {
        $t = Tournament::factory()->active()->create();
        TournamentRound::factory()->for($t)->completed()->create();

        expect($this->sm->canTransitionTournamentTo($t, TournamentStatusEnum::Completed))->toBeNull();
    });
});

describe('round-level transitions', function () {
    it('blocks Setup → InProgress when scenario is not set', function () {
        $t = Tournament::factory()->active()->create();
        $r = TournamentRound::factory()->for($t)->create();
        $p1 = TournamentPlayer::factory()->for($t)->create();
        $p2 = TournamentPlayer::factory()->for($t)->create();
        TournamentGame::factory()->for($r, 'round')->create([
            'player_one_id' => $p1->id, 'player_two_id' => $p2->id,
        ]);

        expect($this->sm->canTransitionRoundTo($r->fresh(), TournamentRoundStatusEnum::InProgress))
            ->toContain('Set the round scenario');
    });

    it('blocks Setup → InProgress when an active player is unpaired', function () {
        $t = Tournament::factory()->active()->create();
        $r = TournamentRound::factory()->for($t)->create([
            'strategy_id' => \App\Models\Strategy::factory()->create()->id,
            'deployment' => 'standard',
            'scheme_pool' => [1, 2, 3],
        ]);
        $p1 = TournamentPlayer::factory()->for($t)->create();
        $p2 = TournamentPlayer::factory()->for($t)->create();
        $p3 = TournamentPlayer::factory()->for($t)->create(['display_name' => 'Lonely Larry']);
        TournamentGame::factory()->for($r, 'round')->create([
            'player_one_id' => $p1->id, 'player_two_id' => $p2->id,
        ]);

        expect($this->sm->canTransitionRoundTo($r->fresh(), TournamentRoundStatusEnum::InProgress))
            ->toContain('Lonely Larry');
    });

    it('counts a bye game as accounting for that player', function () {
        $t = Tournament::factory()->active()->create();
        $r = TournamentRound::factory()->for($t)->create([
            'strategy_id' => \App\Models\Strategy::factory()->create()->id,
            'deployment' => 'standard',
            'scheme_pool' => [1, 2, 3],
        ]);
        $p1 = TournamentPlayer::factory()->for($t)->create();
        $p2 = TournamentPlayer::factory()->for($t)->create();
        $p3 = TournamentPlayer::factory()->for($t)->create();
        TournamentGame::factory()->for($r, 'round')->create([
            'player_one_id' => $p1->id, 'player_two_id' => $p2->id,
        ]);
        // p3 has a bye — should not count as unpaired
        TournamentGame::factory()->for($r, 'round')->bye()->create([
            'player_one_id' => $p3->id, 'player_two_id' => null,
        ]);

        expect($this->sm->canTransitionRoundTo($r->fresh(), TournamentRoundStatusEnum::InProgress))->toBeNull();
    });

    it('ignores dropped players when checking for unpaired', function () {
        $t = Tournament::factory()->active()->create();
        $r = TournamentRound::factory()->for($t)->create([
            'round_number' => 2,
            'strategy_id' => \App\Models\Strategy::factory()->create()->id,
            'deployment' => 'standard',
            'scheme_pool' => [1, 2, 3],
        ]);
        $p1 = TournamentPlayer::factory()->for($t)->create();
        $p2 = TournamentPlayer::factory()->for($t)->create();
        TournamentPlayer::factory()->for($t)->create(['dropped_after_round' => 1]);
        TournamentGame::factory()->for($r, 'round')->create([
            'player_one_id' => $p1->id, 'player_two_id' => $p2->id,
        ]);

        expect($this->sm->canTransitionRoundTo($r->fresh(), TournamentRoundStatusEnum::InProgress))->toBeNull();
    });

    it('blocks Setup → InProgress when no games are paired', function () {
        $t = Tournament::factory()->active()->create();
        $r = TournamentRound::factory()->for($t)->create();

        expect($this->sm->canTransitionRoundTo($r, TournamentRoundStatusEnum::InProgress))
            ->toBe('Generate pairings before starting the round');
    });

    it('allows Setup → InProgress when games exist and scenario is set', function () {
        $t = Tournament::factory()->active()->create();
        $r = TournamentRound::factory()->for($t)->create([
            'strategy_id' => \App\Models\Strategy::factory()->create()->id,
            'deployment' => 'standard',
            'scheme_pool' => [1, 2, 3],
        ]);
        $p = TournamentPlayer::factory()->for($t)->create();
        TournamentGame::factory()->for($r, 'round')->bye()->create([
            'player_one_id' => $p->id,
            'player_two_id' => null,
        ]);

        expect($this->sm->canTransitionRoundTo($r->fresh(), TournamentRoundStatusEnum::InProgress))->toBeNull();
    });

    it('blocks InProgress → Completed when games are unreported', function () {
        $t = Tournament::factory()->active()->create();
        $r = TournamentRound::factory()->for($t)->inProgress()->create();
        $p1 = TournamentPlayer::factory()->for($t)->create();
        $p2 = TournamentPlayer::factory()->for($t)->create();
        TournamentGame::factory()->for($r, 'round')->create(['player_one_id' => $p1->id, 'player_two_id' => $p2->id]);

        expect($this->sm->canTransitionRoundTo($r, TournamentRoundStatusEnum::Completed))
            ->toContain('not yet reported');
    });

    it('allows InProgress → Completed when all games scored', function () {
        $t = Tournament::factory()->active()->create();
        $r = TournamentRound::factory()->for($t)->inProgress()->create();
        $p1 = TournamentPlayer::factory()->for($t)->create();
        $p2 = TournamentPlayer::factory()->for($t)->create();
        TournamentGame::factory()->for($r, 'round')->withScore(5, 3)->create([
            'player_one_id' => $p1->id, 'player_two_id' => $p2->id,
        ]);

        expect($this->sm->canTransitionRoundTo($r, TournamentRoundStatusEnum::Completed))->toBeNull();
    });
});

describe('pairing guards', function () {
    it('blocks Round 1 pairing while tournament is in Draft', function () {
        $t = Tournament::factory()->create();
        $r = TournamentRound::factory()->for($t)->create();

        expect($this->sm->canPairRound($t, $r))->toContain('Round 1 can be paired');
    });

    it('blocks Round 2 pairing until Round 1 is completed', function () {
        $t = Tournament::factory()->active()->create();
        TournamentRound::factory()->for($t)->inProgress()->create(['round_number' => 1]);
        $r2 = TournamentRound::factory()->for($t)->create(['round_number' => 2]);

        expect($this->sm->canPairRound($t, $r2))->toBe('Round 1 must be completed first');
    });

    it('allows Round 2 pairing once Round 1 is completed', function () {
        $t = Tournament::factory()->active()->create();
        TournamentRound::factory()->for($t)->completed()->create(['round_number' => 1]);
        $r2 = TournamentRound::factory()->for($t)->create(['round_number' => 2]);

        expect($this->sm->canPairRound($t, $r2))->toBeNull();
    });
});
