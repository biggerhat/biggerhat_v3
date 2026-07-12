<?php

namespace App\Notifications\Tournament;

use App\Models\Tournament;
use App\Models\TournamentRound;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a player when their round pairing is generated —
 * TournamentRoundController::pair() only reaches players currently on the
 * tournament page live (via BroadcastsTournamentUpdates); anyone not
 * watching learns nothing until they check back.
 */
class TournamentRoundPaired extends Notification
{
    public function __construct(public Tournament $tournament, public TournamentRound $round, public string $opponentName) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tournament_round_paired',
            'message' => "Round {$this->round->round_number} of \"{$this->tournament->name}\" is set — you're playing {$this->opponentName}.",
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
        return 'tournament.round.paired';
    }
}
