<?php

namespace App\Jobs;

use App\Models\Tournament;
use App\Models\TournamentRound;
use App\Services\Discord\TournamentDiscord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Posts one piece of tournament news to the organizer's Discord channel, off the request. Does nothing for events
 * without a webhook, so call sites can dispatch unconditionally.
 */
class PostTournamentToDiscord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 20;

    /** @param  'registration'|'round'|'announcement'|'completed'  $kind */
    public function __construct(public Tournament $tournament, public string $kind, public ?TournamentRound $round = null, public ?string $text = null) {}

    public function handle(TournamentDiscord $discord): void
    {
        $tournament = $this->tournament->fresh();
        if ($tournament === null || ! $tournament->discord_webhook_url) {
            return;
        }

        $embed = match ($this->kind) {
            'registration' => $discord->registrationOpened($tournament),
            'round' => $this->round ? $discord->roundStarted($tournament, $this->round->fresh()) : null,
            'announcement' => $this->text ? $discord->announcement($tournament, $this->text) : null,
            'completed' => $discord->completed($tournament),
            default => null,
        };

        if ($embed !== null) {
            $discord->send($tournament, $embed);
        }
    }

    /** Dispatch only when the event has a webhook, so quiet events never touch the queue. */
    public static function whenConnected(Tournament $tournament, string $kind, ?TournamentRound $round = null, ?string $text = null): void
    {
        if ($tournament->discord_webhook_url) {
            self::dispatch($tournament, $kind, $round, $text);
        }
    }
}
