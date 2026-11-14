<?php

namespace App\Console\Commands;

use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Models\TournamentGame;
use App\Models\TournamentRound;
use App\Models\User;
use App\Notifications\Tournament\TournamentRoundTimeWarning;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendTournamentRoundTimeWarnings extends Command
{
    protected $signature = 'tournaments:round-time-warnings';

    protected $description = 'Notify players with an unfinished game when a running round enters its last-turn window';

    public function handle(): int
    {
        $sent = 0;

        TournamentRound::query()
            ->where('status', TournamentRoundStatusEnum::InProgress)
            ->whereNotNull('started_at')
            ->whereNull('last_turn_notified_at')
            ->with('tournament')
            ->get()
            ->each(function (TournamentRound $round) use (&$sent) {
                $tournament = $round->tournament;
                $warnMinutes = (int) ($tournament->rulesetDefinition()->timeRules['last_turn_minutes'] ?? 15);
                $limit = (int) $tournament->round_time_limit;
                $warnAt = $round->started_at->copy()->addMinutes($limit - $warnMinutes);

                // Past the limit already (organizer ran the clock long): too late to be a useful warning.
                if (now()->lt($warnAt) || now()->gte($round->started_at->copy()->addMinutes($limit))) {
                    return;
                }

                // Claim the round under a lock so overlapping scheduler runs cannot notify twice.
                $claimed = DB::transaction(function () use ($round) {
                    $locked = TournamentRound::lockForUpdate()->find($round->id);
                    if (! $locked || $locked->status !== TournamentRoundStatusEnum::InProgress || $locked->last_turn_notified_at) {
                        return false;
                    }
                    $locked->update(['last_turn_notified_at' => now()]);

                    return true;
                });
                if (! $claimed) {
                    return;
                }

                $games = $round->games()
                    ->where('is_bye', false)
                    ->whereNotNull('player_one_id')
                    ->whereNotNull('player_two_id')
                    ->where('result', TournamentGameResultEnum::Pending)
                    ->with(['playerOne.members:id,tournament_player_id,user_id', 'playerTwo.members:id,tournament_player_id,user_id'])
                    ->get();

                $userIds = $games
                    ->flatMap(fn (TournamentGame $g) => [...$g->playerOne->linkedUserIds(), ...$g->playerTwo->linkedUserIds()])
                    ->unique();

                User::whereIn('id', $userIds)->get()->each(function (User $user) use ($tournament, $round, $warnMinutes, &$sent) {
                    $user->notify(new TournamentRoundTimeWarning($tournament, $round, $warnMinutes));
                    $sent++;
                });
            });

        $this->info("Sent {$sent} time warning(s).");

        return self::SUCCESS;
    }
}
