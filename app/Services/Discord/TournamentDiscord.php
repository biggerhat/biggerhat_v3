<?php

namespace App\Services\Discord;

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentRound;
use App\Services\TournamentStandingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts tournament news to the organizer's Discord channel through a channel webhook: registration opened, a round
 * started with its pairings, a notice from the organizer, and the final podium. The webhook URL is the organizer's own
 * secret (stored encrypted); only discord.com webhook URLs are accepted, redirects are not followed, and a failing or
 * slow Discord never blocks or breaks the tournament action that triggered the post.
 */
class TournamentDiscord
{
    /** What a webhook URL has to look like (also enforced by TournamentDiscordRequest). */
    public const URL_PATTERN = '#^https://(?:ptb\.|canary\.)?discord(?:app)?\.com/api/webhooks/\d{5,25}/[\w-]{20,200}$#';

    private const COLOR = 0xC9A227;

    public function __construct(private readonly TournamentStandingsService $standings) {}

    /** Send one embed; false when the event has no webhook or Discord refused it. */
    public function send(Tournament $tournament, array $embed): bool
    {
        $url = $tournament->discord_webhook_url;
        if (! is_string($url) || preg_match(self::URL_PATTERN, $url) !== 1) {
            return false;
        }

        try {
            $response = Http::timeout(5)->withoutRedirecting()->post($url, [
                'username' => 'BiggerHat Tournaments',
                'allowed_mentions' => ['parse' => []], // never ping @everyone or roles from event text
                'embeds' => [$embed + ['color' => self::COLOR]],
            ]);

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning('Discord webhook post failed for tournament '.$tournament->id.': '.$e->getMessage());

            return false;
        }
    }

    /** @return array<string, mixed> */
    public function test(Tournament $tournament): array
    {
        return $this->base($tournament, 'Discord is connected', 'Updates for this event will be posted in this channel: registration opening, each round as it starts, announcements and the final results.');
    }

    /** @return array<string, mixed> */
    public function registrationOpened(Tournament $tournament): array
    {
        $fields = [
            ['name' => 'Date', 'value' => $tournament->event_date->format('D j M Y'), 'inline' => true],
            ['name' => 'Format', 'value' => $tournament->rulesetDefinition()->label, 'inline' => true],
        ];
        if ($tournament->location) {
            $fields[] = ['name' => 'Where', 'value' => $this->clip($tournament->location, 200), 'inline' => true];
        }
        $fields[] = ['name' => 'Players', 'value' => $tournament->max_players ? "Up to {$tournament->max_players}" : 'No limit', 'inline' => true];

        return $this->base($tournament, 'Registration is open', $tournament->description ?: null) + ['fields' => $fields];
    }

    /** @return array<string, mixed> */
    public function roundStarted(Tournament $tournament, TournamentRound $round): array
    {
        $round->loadMissing('games.playerOne:id,display_name', 'games.playerTwo:id,display_name');
        $lines = $round->games->map(function (TournamentGame $g): string {
            if ($g->is_bye) {
                return "Bye — {$g->playerOne->display_name}";
            }

            return ($g->table_number ? "**Table {$g->table_number}** " : '')."{$g->playerOne->display_name} vs {$g->playerTwo?->display_name}";
        })->all();

        return $this->base($tournament, "{$round->label} has started", $this->clip(implode("\n", $lines), 3500) ?: null);
    }

    /** @return array<string, mixed> */
    public function announcement(Tournament $tournament, string $text): array
    {
        return $this->base($tournament, 'Announcement', $this->clip($text, 1000));
    }

    /** @return array<string, mixed> */
    public function completed(Tournament $tournament): array
    {
        $podium = collect($this->standings->compute($tournament))
            ->filter(fn (array $row) => ! $row['is_ringer'] && $row['rank'] !== null && $row['rank'] <= 3)
            ->map(fn (array $row) => ['1' => '🥇', '2' => '🥈', '3' => '🥉'][(string) $row['rank']].' '.$row['display_name'].' — '.$row['total_tp'].' TP')
            ->values()
            ->all();

        return $this->base($tournament, 'Final results', $podium === [] ? 'The tournament is finished.' : implode("\n", $podium))
            + ['fields' => [['name' => 'Full results', 'value' => '[Standings]('.route('tournaments.view', $tournament).') · [PDF]('.route('tournaments.results_pdf', $tournament).')']]];
    }

    /** @return array<string, mixed> */
    private function base(Tournament $tournament, string $title, ?string $description): array
    {
        $embed = [
            'title' => $this->clip($tournament->name.' — '.$title, 250),
            'url' => route('tournaments.view', $tournament),
            'footer' => ['text' => 'BiggerHat · Tournament Tracker'],
        ];
        if ($description !== null) {
            $embed['description'] = $this->clip($description, 3900);
        }

        return $embed;
    }

    private function clip(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }
}
