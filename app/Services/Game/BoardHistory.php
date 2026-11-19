<?php

namespace App\Services\Game;

use App\Models\Game;

/**
 * Where the models and markers were at the end of each turn, kept in the game's board state so the board can show the table as it stood
 * (a read-only look back). Only moved models have a saved position; the others are drawn where the board puts them by default.
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
        ];
        $locked->update(['board_state' => array_merge($board, ['history' => $history])]);
    }
}
