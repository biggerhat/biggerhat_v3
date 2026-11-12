<?php

namespace App\Tournaments;

use App\Enums\EncounterTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use App\Tournaments\Rulesets\Ruleset;
use Illuminate\Support\Facades\DB;

/**
 * Back-fills a finished event from a CSV of its games, in the same shape as the tournament export
 * (round, table, player_one, player_one_faction, player_one_master, player_one_vp, [player_one_<component>_vp], player_two, ... , result, is_bye, is_forfeit).
 *
 * parse() reads and checks the file without touching the database and returns a plan (rounds, players, games, errors, warnings);
 * create() turns an error-free plan into a completed tournament with its rounds, players and games, so standings, results sheet
 * and profile history all work for it.
 */
class ResultsImport
{
    public const MAX_ROUNDS = 10;

    public const MAX_ROWS = 2000;

    public const MAX_PLAYERS = 256;

    private const MAX_ERRORS = 25;

    /**
     * @return array{rounds: array<int, list<array<string, mixed>>>, players: array<string, array{name: string, faction: string|null}>, errors: list<string>, warnings: list<string>, game_count: int}
     */
    public function parse(string $csv, Ruleset $ruleset): array
    {
        $plan = ['rounds' => [], 'players' => [], 'errors' => [], 'warnings' => [], 'game_count' => 0];

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv);
        rewind($handle);

        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header === false || $header === [null]) {
            $plan['errors'][] = 'The file is empty.';

            return $plan;
        }
        $columns = array_flip(array_map(fn ($h) => preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string) $h))) ?? '', $header));
        foreach (['round', 'player_one', 'player_two'] as $required) {
            if (! isset($columns[$required])) {
                $plan['errors'][] = "Missing the \"{$required}\" column.";
            }
        }
        $components = array_values(array_filter($ruleset->scoreFieldKeys(), fn (string $k) => $k !== 'vp'));
        $usesComponents = $components !== [] && collect($components)->every(fn (string $k) => isset($columns["player_one_{$k}_vp"], $columns["player_two_{$k}_vp"]));
        if (! $usesComponents && ! isset($columns['player_one_vp'], $columns['player_two_vp'])) {
            $plan['errors'][] = 'Missing the score columns: player_one_vp and player_two_vp'.($components ? ' (or one column per score component)' : '').'.';
        }
        if ($plan['errors'] !== []) {
            return $plan;
        }

        $max = array_sum(array_column($ruleset->scoreFields, 'max'));
        $line = 1;
        $rows = 0;
        $seenInRound = [];
        $factionWarned = false;
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            if (++$rows > self::MAX_ROWS) {
                $this->error($plan, 'The file has more than '.self::MAX_ROWS.' games.');
                break;
            }
            $get = fn (string $key): string => isset($columns[$key]) ? trim((string) ($row[$columns[$key]] ?? '')) : '';

            $round = $get('round');
            if (! ctype_digit($round) || (int) $round < 1 || (int) $round > self::MAX_ROUNDS) {
                $this->error($plan, "Line {$line}: the round must be a number from 1 to ".self::MAX_ROUNDS.'.');

                continue;
            }
            $round = (int) $round;
            $isBye = $this->truthy($get('is_bye'));
            $isForfeit = $this->truthy($get('is_forfeit'));
            $one = $get('player_one');
            $two = $isBye ? '' : $get('player_two');
            if ($one === '' || (! $isBye && $two === '')) {
                $this->error($plan, "Line {$line}: both players are needed (mark a bye with is_bye = true).");

                continue;
            }
            if (mb_strtolower($one) === mb_strtolower($two)) {
                $this->error($plan, "Line {$line}: {$one} cannot play themself.");

                continue;
            }

            $scores = [null, null];
            $breakdown = [null, null];
            if (! $isBye && ! $isForfeit) {
                foreach (['player_one' => 0, 'player_two' => 1] as $side => $i) {
                    if ($usesComponents) {
                        $parts = [];
                        foreach ($components as $k) {
                            $value = $get("{$side}_{$k}_vp");
                            $parts[$k] = ctype_digit($value) ? (int) $value : null;
                        }
                        if (in_array(null, $parts, true)) {
                            $this->error($plan, "Line {$line}: the {$side} score parts must be whole numbers.");

                            continue 2;
                        }
                        $breakdown[$i] = $parts;
                        $scores[$i] = array_sum($parts);
                    } else {
                        $value = $get("{$side}_vp");
                        if (! ctype_digit($value)) {
                            $this->error($plan, "Line {$line}: {$side}_vp must be a whole number.");

                            continue 2;
                        }
                        $scores[$i] = (int) $value;
                    }
                    if ($scores[$i] > $max) {
                        $this->error($plan, "Line {$line}: a score of {$scores[$i]} is more than this format allows ({$max}).");

                        continue 2;
                    }
                }
            }

            foreach ([$one, $two] as $name) {
                if ($name === '') {
                    continue;
                }
                $key = mb_strtolower($name);
                if (isset($seenInRound[$round][$key])) {
                    $this->error($plan, "Line {$line}: {$name} is already paired in round {$round}.");

                    continue 2;
                }
            }
            $factions = [null, null];
            if ($ruleset->declaration === 'faction') {
                foreach (['player_one' => 0, 'player_two' => 1] as $side => $i) {
                    $value = $get("{$side}_faction");
                    if ($value === '') {
                        continue;
                    }
                    $faction = $this->faction($value);
                    if ($faction === null && ! $factionWarned) {
                        $plan['warnings'][] = "Line {$line}: \"{$value}\" is not a faction I know, so factions are left blank where unrecognised.";
                        $factionWarned = true;
                    }
                    $factions[$i] = $faction;
                }
            }

            foreach ([[$one, $factions[0]], [$two, $factions[1]]] as [$name, $faction]) {
                if ($name === '') {
                    continue;
                }
                $key = mb_strtolower($name);
                $seenInRound[$round][$key] = true;
                $plan['players'][$key] ??= ['name' => $name, 'faction' => null];
                $plan['players'][$key]['faction'] ??= $faction;
            }

            $result = TournamentGameResultEnum::tryFrom(strtolower($get('result')));
            $plan['rounds'][$round][] = [
                'table' => ctype_digit($get('table')) ? (int) $get('table') : null,
                'one' => mb_strtolower($one),
                'two' => $isBye ? null : mb_strtolower($two),
                'is_bye' => $isBye,
                'is_forfeit' => $isForfeit,
                'score_one' => $scores[0],
                'score_two' => $scores[1],
                'breakdown_one' => $breakdown[0],
                'breakdown_two' => $breakdown[1],
                'faction_one' => $factions[0],
                'faction_two' => $factions[1],
                'master_one' => $get('player_one_master') ?: null,
                'master_two' => $get('player_two_master') ?: null,
                'agreed' => $result === TournamentGameResultEnum::Agreed,
            ];
            $plan['game_count']++;
        }
        fclose($handle);

        if ($plan['rounds'] === [] && $plan['errors'] === []) {
            $plan['errors'][] = 'The file has no games.';
        }
        ksort($plan['rounds']);
        if ($plan['rounds'] !== [] && array_keys($plan['rounds']) !== range(1, count($plan['rounds']))) {
            $plan['errors'][] = 'The rounds must be numbered 1, 2, 3… without gaps.';
        }
        if (count($plan['players']) > self::MAX_PLAYERS) {
            $plan['errors'][] = 'The file has more than '.self::MAX_PLAYERS.' players.';
        }
        if ($ruleset->declaration === 'allegiance') {
            $plan['warnings'][] = 'Allegiances are not imported for this format; standings and results are.';
        }

        return $plan;
    }

    /**
     * Build the completed tournament for a checked plan.
     *
     * @param  array{name: string, event_date: string, location?: string|null, encounter_size: int, season?: string|null}  $meta
     * @param  array{rounds: array<int, list<array<string, mixed>>>, players: array<string, array{name: string, faction: string|null}>}  $plan
     */
    public function create(User $user, Ruleset $ruleset, array $meta, array $plan): Tournament
    {
        return DB::transaction(function () use ($user, $ruleset, $meta, $plan): Tournament {
            $tournament = Tournament::create([
                'name' => $meta['name'],
                'ruleset' => $ruleset->key,
                'event_date' => $meta['event_date'],
                'location' => $meta['location'] ?? null,
                'encounter_size' => $meta['encounter_size'],
                'encounter_type' => EncounterTypeEnum::Traditional,
                'season' => $meta['season'] ?? PoolSeasonEnum::defaultSeason()->value,
                'planned_rounds' => count($plan['rounds']),
                'round_time_limit' => $ruleset->defaultRoundTimeLimit ?? 135,
                'creator_id' => $user->id,
                'status' => TournamentStatusEnum::Completed,
            ]);
            $tournament->organizers()->attach($user->id);

            $players = [];
            foreach ($plan['players'] as $key => $p) {
                $players[$key] = TournamentPlayer::create([
                    'tournament_id' => $tournament->id,
                    'display_name' => $p['name'],
                    'faction' => $p['faction'],
                ]);
            }

            $when = $tournament->event_date;
            foreach ($plan['rounds'] as $number => $games) {
                $round = TournamentRound::create([
                    'tournament_id' => $tournament->id,
                    'round_number' => $number,
                    'status' => TournamentRoundStatusEnum::Completed,
                    'started_at' => $when,
                    'completed_at' => $when,
                ]);
                $table = 0;
                foreach ($games as $g) {
                    $table++;
                    $one = $players[$g['one']];
                    $two = $g['two'] !== null ? $players[$g['two']] : null;
                    $forfeiter = null;
                    if ($g['is_forfeit'] && $two !== null) {
                        $forfeiter = $two;
                    }
                    TournamentGame::create([
                        'tournament_round_id' => $round->id,
                        'player_one_id' => $one->id,
                        'player_two_id' => $two?->id,
                        'table_number' => $g['is_bye'] ? null : ($g['table'] ?? $table),
                        'is_bye' => $g['is_bye'],
                        'is_manual' => false,
                        'is_forfeit' => $g['is_forfeit'] && $two !== null,
                        'forfeit_player_id' => $forfeiter?->id,
                        'player_one_vp' => $g['score_one'],
                        'player_two_vp' => $g['score_two'],
                        'score_breakdown' => $g['breakdown_one'] !== null ? ['player_one' => $g['breakdown_one'], 'player_two' => $g['breakdown_two']] : null,
                        'result' => $g['is_forfeit'] && $two !== null ? TournamentGameResultEnum::Forfeited : ($g['agreed'] ? TournamentGameResultEnum::Agreed : TournamentGameResultEnum::Completed),
                        'player_one_faction' => $g['faction_one'] ?? $plan['players'][$g['one']]['faction'],
                        'player_two_faction' => $two !== null ? ($g['faction_two'] ?? $plan['players'][$g['two']]['faction']) : null,
                        'player_one_master' => $g['master_one'],
                        'player_two_master' => $g['master_two'],
                    ]);
                }
            }

            return $tournament;
        });
    }

    /** @param array{errors: list<string>} $plan */
    private function error(array &$plan, string $message): void
    {
        if (count($plan['errors']) < self::MAX_ERRORS) {
            $plan['errors'][] = $message;
        } elseif (count($plan['errors']) === self::MAX_ERRORS) {
            $plan['errors'][] = 'More problems were found; fix these first.';
        }
    }

    private function truthy(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'y'], true);
    }

    private function faction(string $value): ?string
    {
        $needle = preg_replace('/[^a-z0-9]+/', '', strtolower($value));
        foreach (FactionEnum::cases() as $case) {
            if (preg_replace('/[^a-z0-9]+/', '', strtolower($case->value)) === $needle || preg_replace('/[^a-z0-9]+/', '', strtolower($case->label())) === $needle) {
                return $case->value;
            }
        }

        return null;
    }
}
