<?php

namespace App\Notifications\Tournament;

use App\Models\Tournament;
use App\Models\TournamentRound;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to players whose game in a running round has no result yet when the round clock
 * reaches the format's "last turn" window (see `Ruleset::$timeRules`).
 */
class TournamentRoundTimeWarning extends Notification
{
    public function __construct(public Tournament $tournament, public TournamentRound $round, public int $minutesLeft) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tournament_round_time_warning',
            'message' => "About {$this->minutesLeft} minutes left in round {$this->round->round_number} of \"{$this->tournament->name}\" — wrap up your game and report the result.",
            'actor' => null,
            'action_url' => route('tournaments.view', $this->tournament),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'tournament.round.time_warning';
    }
}
