<?php

namespace App\Support\BattleReports;

use App\Models\BattleReport;
use App\Tournaments\RichText;

/**
 * What a moderator read when they approved a battle report, and the test of whether a later edit needs them to read it again.
 *
 * An edit to a public report counts as a new thing to read when it changes the title or the game, shows a scoreboard that was hidden, adds an image or a link that was
 * not there, or adds more than a few words. A typo or a reworded sentence does not. Edits are always measured against the approved
 * text, not the previous save, so small changes cannot add up to a rewrite.
 */
class ApprovedSnapshot
{
    private const MIN_NEW_WORDS = 8;

    private const MAX_NEW_WORDS = 40;

    /**
     * @return array{title: string, link: string|null, show_game: bool, text: string, media: list<string>}
     */
    public static function capture(BattleReport $report): array
    {
        return self::of($report->title, $report->linkKey(), $report->show_game, $report->content);
    }

    /**
     * @param  array<string, mixed>|null  $content
     * @return array{title: string, link: string|null, show_game: bool, text: string, media: list<string>}
     */
    public static function of(string $title, ?string $link, bool $showGame, ?array $content): array
    {
        return ['title' => $title, 'link' => $link, 'show_game' => $showGame, 'text' => RichText::plainText($content), 'media' => RichText::media($content)];
    }

    /**
     * Does a report with this title, game and content need to be read again, given what was approved?
     *
     * @param  array<string, mixed>|null  $approved
     * @param  array<string, mixed>|null  $content
     */
    public static function needsReview(?array $approved, string $title, ?string $link, bool $showGame, ?array $content): bool
    {
        if ($approved === null || ! isset($approved['text'], $approved['media'])) {
            return true; // Nothing to compare with: treat any change as new.
        }
        if (($approved['title'] ?? null) !== $title || ($approved['link'] ?? null) !== $link) {
            return true;
        }
        // Showing a scoreboard the moderator did not see (it was hidden when they approved) is new to read; hiding one is not.
        if ($showGame && $link !== null && ! ($approved['show_game'] ?? false)) {
            return true;
        }
        if (array_diff(RichText::media($content), (array) $approved['media']) !== []) {
            return true;
        }

        $before = self::words((string) $approved['text']);
        $added = 0;
        foreach (self::words(RichText::plainText($content)) as $word => $count) {
            $added += max(0, $count - ($before[$word] ?? 0));
        }
        $allowed = min(self::MAX_NEW_WORDS, max(self::MIN_NEW_WORDS, (int) ceil(0.05 * array_sum($before))));

        return $added > $allowed;
    }

    /** @return array<string, int> */
    private static function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}\']+/u', mb_strtolower($text), $m);

        return array_count_values($m[0]);
    }
}
