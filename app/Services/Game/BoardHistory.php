<?php

namespace App\Services\Game;

use App\Models\Game;
use App\Models\GameCrewMember;

/**
 * Where the models and markers were at the end of each turn, kept in the game's board state so the board can show the table as it stood
 * (a read-only look back), with each model's health, tokens and whether it was killed. Only moved models have a saved position; the others
 * are drawn where the board puts them by default.
 */
class BoardHistory
{
    /** Save the table as it stands for the turn that is ending. Call with the game row locked. */
    public function snapshot(Game $locked): void
    {
        $board = $locked->board_state ?? [];
        $history = $board['history'] ?? [];
        $history[(string) $locked->current_turn] = [
            'positions' => $board['positions'] ?? (object) [],
            'markers' => $board['markers'] ?? [],
            // how each model stood too (activation is cleared as each player submits, so it is not kept)
            'models' => GameCrewMember::query()->where('game_id', $locked->id)->get(['id', 'current_health', 'max_health', 'is_killed', 'attached_tokens'])
                ->mapWithKeys(fn (GameCrewMember $m) => [(string) $m->id => [
                    'health' => $m->current_health,
                    'max_health' => $m->max_health,
                    'killed' => (bool) $m->is_killed,
                    'tokens' => collect($m->attached_tokens ?? [])
                        ->filter(fn ($t) => is_array($t) && isset($t['id']))
                        ->map(fn (array $t) => ['id' => (int) $t['id'], 'name' => (string) ($t['name'] ?? 'Token')])
                        ->values()
                        ->all(),
                ]])
                ->all(),
        ];
        $locked->update(['board_state' => array_merge($board, ['history' => $history])]);
    }
}
