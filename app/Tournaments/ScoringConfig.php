<?php

namespace App\Tournaments;

/**
 * Immutable scoring parameters for a tournament ruleset.
 *
 * The same parameter set describes every supported format; a ruleset is just a
 * different set of numbers (see config/tournaments.php). Per-game maths lives in
 * ScoringEngine — this class is data only.
 *
 * Per-game tournament points (TP):
 *   tp = base_tp + (win_tp | tie_tp | loss_tp) + (diff_adds_to_tp ? diff : 0)
 *
 * Gaining Grounds: base 0, win 3, tie 1, loss 0, DIFF not added.
 * Fields of Glory: base 10, win 2, tie 0, loss 0, DIFF (capped ±8) added.
 */
final class ScoringConfig
{
    public function __construct(
        public readonly int $baseTp = 0,
        public readonly int $winTp = 3,
        public readonly int $tieTp = 1,
        public readonly int $lossTp = 0,
        public readonly bool $diffAddsToTp = false,
        /** Maximum absolute per-game DIFF; null = uncapped. */
        public readonly ?int $diffCap = null,
        public readonly int $byeTp = 3,
        public readonly int $byeDiff = 4,
        public readonly int $byeVp = 6,
        /** VP awarded to the winner (and DIFF swing) when a game is forfeited. */
        public readonly int $forfeitVp = 11,
        /** TP the forfeiting player receives. */
        public readonly int $forfeitLoserTp = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data  snake_case keys; missing keys fall back to defaults
     */
    public static function fromArray(array $data): self
    {
        $d = new self;

        return new self(
            baseTp: (int) ($data['base_tp'] ?? $d->baseTp),
            winTp: (int) ($data['win_tp'] ?? $d->winTp),
            tieTp: (int) ($data['tie_tp'] ?? $d->tieTp),
            lossTp: (int) ($data['loss_tp'] ?? $d->lossTp),
            diffAddsToTp: (bool) ($data['diff_adds_to_tp'] ?? $d->diffAddsToTp),
            diffCap: array_key_exists('diff_cap', $data) ? ($data['diff_cap'] === null ? null : (int) $data['diff_cap']) : $d->diffCap,
            byeTp: (int) ($data['bye_tp'] ?? $d->byeTp),
            byeDiff: (int) ($data['bye_diff'] ?? $d->byeDiff),
            byeVp: (int) ($data['bye_vp'] ?? $d->byeVp),
            forfeitVp: (int) ($data['forfeit_vp'] ?? $d->forfeitVp),
            forfeitLoserTp: (int) ($data['forfeit_loser_tp'] ?? $d->forfeitLoserTp),
        );
    }

    /**
     * @return array<string, int|bool|null>
     */
    public function toArray(): array
    {
        return [
            'base_tp' => $this->baseTp,
            'win_tp' => $this->winTp,
            'tie_tp' => $this->tieTp,
            'loss_tp' => $this->lossTp,
            'diff_adds_to_tp' => $this->diffAddsToTp,
            'diff_cap' => $this->diffCap,
            'bye_tp' => $this->byeTp,
            'bye_diff' => $this->byeDiff,
            'bye_vp' => $this->byeVp,
            'forfeit_vp' => $this->forfeitVp,
            'forfeit_loser_tp' => $this->forfeitLoserTp,
        ];
    }

    /**
     * Layer overrides (snake_case keys) over this config. Null values are ignored
     * so "unset" overrides never clobber ruleset defaults — except diff_cap, where
     * null is meaningful (uncapped) and is honoured when the key is present.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        $merged = $this->toArray();
        foreach ($overrides as $key => $value) {
            if (! array_key_exists($key, $merged)) {
                continue;
            }
            if ($value === null && $key !== 'diff_cap') {
                continue;
            }
            $merged[$key] = $value;
        }

        return self::fromArray($merged);
    }
}
