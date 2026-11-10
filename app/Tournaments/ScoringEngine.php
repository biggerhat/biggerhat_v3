<?php

namespace App\Tournaments;

/**
 * Pure per-game scoring maths driven by a ScoringConfig. No persistence, no
 * knowledge of any particular game — the numbers come from the ruleset.
 */
final class ScoringEngine
{
    public function __construct(public readonly ScoringConfig $config) {}

    /**
     * Score one side of a played game.
     *
     * @return array{tp: int, diff: int, vp: int, outcome: 'win'|'tie'|'loss'}
     */
    public function game(int $myVp, int $oppVp): array
    {
        $diff = $this->capDiff($myVp - $oppVp);
        $outcome = $myVp > $oppVp ? 'win' : ($myVp === $oppVp ? 'tie' : 'loss');

        return [
            'tp' => $this->tp($outcome, $diff),
            'diff' => $diff,
            'vp' => $myVp,
            'outcome' => $outcome,
        ];
    }

    /** @return array{tp: int, diff: int, vp: int} */
    public function bye(): array
    {
        return ['tp' => $this->config->byeTp, 'diff' => $this->config->byeDiff, 'vp' => $this->config->byeVp];
    }

    /** The side that did NOT forfeit. @return array{tp: int, diff: int, vp: int} */
    public function forfeitWin(): array
    {
        $diff = $this->capDiff($this->config->forfeitVp);

        return ['tp' => $this->tp('win', $diff), 'diff' => $diff, 'vp' => $this->config->forfeitVp];
    }

    /** The forfeiting side. @return array{tp: int, diff: int, vp: int} */
    public function forfeitLoss(): array
    {
        return ['tp' => $this->config->forfeitLoserTp, 'diff' => $this->capDiff(-$this->config->forfeitVp), 'vp' => 0];
    }

    /** DIFF penalty for an un-dropped player who sat out a completed round. */
    public function missedRoundDiff(): int
    {
        return $this->config->byeDiff;
    }

    private function tp(string $outcome, int $diff): int
    {
        $c = $this->config;
        $outcomeTp = match ($outcome) {
            'win' => $c->winTp,
            'tie' => $c->tieTp,
            default => $c->lossTp,
        };

        return $c->baseTp + $outcomeTp + ($c->diffAddsToTp ? $diff : 0);
    }

    private function capDiff(int $diff): int
    {
        $cap = $this->config->diffCap;

        return $cap === null ? $diff : max(-$cap, min($cap, $diff));
    }
}
