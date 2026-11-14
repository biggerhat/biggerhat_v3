<?php

namespace App\Tournaments\Analytics;

/**
 * Turns the analytics payloads into CSV: several small tables in one file, each opened by a title row and
 * separated by a blank line, which spreadsheets handle fine and which keeps one download per screen.
 */
final class InsightsCsv
{
    /**
     * @param  resource  $out
     * @param  array<string, array{0: list<string>, 1: list<list<mixed>>}>  $sections  title => [headers, rows]
     */
    public static function write($out, array $sections): void
    {
        $first = true;
        foreach ($sections as $title => [$headers, $rows]) {
            if ($rows === []) {
                continue;
            }
            if (! $first) {
                fputcsv($out, []);
            }
            $first = false;
            fputcsv($out, [self::cell($title)]);
            fputcsv($out, array_map(self::cell(...), $headers));
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::cell(...), $row));
            }
        }
    }

    /**
     * A name that starts with = + - or @ would run as a formula when the file is opened in a spreadsheet.
     */
    public static function cell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $insights  EventInsights::forEvent()
     * @return array<string, array{0: list<string>, 1: list<list<mixed>>}>
     */
    public static function event(array $insights): array
    {
        $g = $insights['games'];
        $f = $insights['field'];

        return [
            'Turnout' => [['metric', 'value'], array_map(fn ($k) => [$k, $f[$k]], array_keys($f))],
            'Games' => [['metric', 'value'], [
                ['games_played', $g['contested']], ['byes', $g['byes']], ['forfeits', $g['forfeits']], ['ties', $g['ties']],
                ['avg_vp_per_player', $g['avg_vp']], ['avg_margin', $g['avg_margin']], ['close_games', $g['close']], ['close_pct', $g['close_pct']],
                ...array_map(fn ($c) => ['avg_'.$c['key'].'_per_player', $c['avg']], $g['components']),
            ]],
            'Margins' => [['margin', 'games'], array_map(fn ($b) => [$b['label'], $b['count']], $g['margin_buckets'])],
            'Rounds' => [['round', 'games', 'avg_vp', 'avg_margin', 'forfeits', 'minutes', 'strategy', 'deployment'], array_map(
                fn ($r) => [$r['label'], $r['games'], $r['avg_vp'], $r['avg_margin'], $r['forfeits'], $r['minutes'], $r['strategy'], $r['deployment']],
                $insights['rounds'],
            )],
            'Factions' => self::breakdown($insights['factions']),
            'Leaders' => self::breakdown($insights['leaders']),
            ...self::scenario($insights),
        ];
    }

    /**
     * @param  array<string, mixed>  $data  OrganizerInsights::forUser() / forSeries()
     * @return array<string, array{0: list<string>, 1: list<list<mixed>>}>
     */
    public static function overview(array $data): array
    {
        return [
            'Summary' => [['metric', 'value'], array_map(fn ($k) => [$k, $data['summary'][$k]], array_keys($data['summary']))],
            'Events' => [['name', 'date', 'status', 'format', 'players', 'returning', 'new', 'rounds', 'games'], array_map(
                fn ($e) => [$e['name'], $e['date'], $e['status'], $e['ruleset'], $e['players'], $e['returning'], $e['new'], $e['rounds'], $e['games']],
                $data['events'],
            )],
            'Factions' => self::breakdown($data['factions']),
            'Regulars' => [['name', 'events'], array_map(fn ($r) => [$r['name'], $r['events']], $data['regulars'])],
            ...self::scenario($data),
        ];
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    private static function breakdown(array $rows): array
    {
        return [['name', 'players', 'games', 'wins', 'losses', 'ties', 'win_rate_pct'], array_map(
            fn ($r) => [$r['label'], $r['players'], $r['games'], $r['wins'], $r['losses'], $r['ties'], $r['win_rate']],
            $rows,
        )];
    }

    /** @return array<string, array{0: list<string>, 1: list<list<mixed>>}> */
    private static function scenario(array $data): array
    {
        return [
            'Strategies' => [['strategy', 'rounds', 'games', 'avg_vp', 'avg_margin'], array_map(
                fn ($s) => [$s['name'], $s['rounds'], $s['games'], $s['avg_vp'], $s['avg_margin']],
                $data['strategies'],
            )],
            'Schemes (Game Tracker games only)' => [['scheme', 'picked', 'scored', 'discarded', 'points', 'avg_points_when_scored', 'score_rate_pct'], array_map(
                fn ($s) => [$s['name'], $s['picked'], $s['scored'], $s['discarded'], $s['points'], $s['avg_points'], $s['score_rate']],
                $data['schemes']['rows'],
            )],
        ];
    }
}
