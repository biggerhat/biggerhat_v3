<?php

/*
 * Tournament rulesets.
 *
 * A ruleset is data: scoring parameters (see App\Tournaments\ScoringConfig),
 * ordered standings tiebreakers, and planning defaults. To support a new game,
 * add an entry here — no service changes needed. `game_system` is a free-form
 * string and is deliberately NOT tied to GameSystemEnum.
 *
 * Tiebreaker keys (applied after TP): 'sos' (strength of schedule), 'diff', 'vp'.
 */
return [
    'default' => 'malifaux_gaining_grounds',

    'rulesets' => [
        // Malifaux — Gaining Grounds One. Win 3 / tie 1 / loss 0; TP → DIFF → VP.
        'malifaux_gaining_grounds' => [
            'label' => 'Malifaux — Gaining Grounds',
            'game_system' => 'malifaux',
            'scoring' => [
                'base_tp' => 0, 'win_tp' => 3, 'tie_tp' => 1, 'loss_tp' => 0,
                'diff_adds_to_tp' => false, 'diff_cap' => null,
                'bye_tp' => 3, 'bye_diff' => 4, 'bye_vp' => 6,
                'forfeit_vp' => 11, 'forfeit_loser_tp' => 0,
            ],
            'tiebreakers' => ['diff', 'vp'],
            // The TO may flip to TP → SoS → DIFF → VP (tournaments.tiebreaker_mode).
            'tiebreaker_toggle' => true,
            'suggested_rounds' => [[4, 15, 3], [16, 32, 4], [33, null, 5]],
            'default_round_time_limit' => 135,
            'game_size' => ['label' => 'Encounter Size (SS)', 'default' => 50, 'min' => 20, 'max' => 100, 'step' => 5],
            'uses_season' => true,
            'uses_encounter_type' => true,
        ],

        // The Other Side — Fields of Glory v3.0. 10 base TP + capped DIFF + 2 for a win; TP → SoS.
        'tos_fields_of_glory' => [
            'label' => 'The Other Side — Fields of Glory',
            'game_system' => 'tos',
            'scoring' => [
                'base_tp' => 10, 'win_tp' => 2, 'tie_tp' => 0, 'loss_tp' => 0,
                'diff_adds_to_tp' => true, 'diff_cap' => 8,
                'bye_tp' => 19, 'bye_diff' => 7, 'bye_vp' => 7,
                'forfeit_vp' => 8, 'forfeit_loser_tp' => 0,
            ],
            'tiebreakers' => ['sos'],
            'tiebreaker_toggle' => false,
            'suggested_rounds' => [[4, 15, 3], [16, 32, 4], [33, null, 5]],
            // 1 Commander: 90 min, 2 Commanders: 135 min (both including setup).
            'default_round_time_limit' => 90,
            // Garrison pools are 40-75 Scrip (One Commander 40 … Two Commanders 75).
            'game_size' => ['label' => 'Game Size (Scrip)', 'default' => 40, 'min' => 20, 'max' => 100, 'step' => 5],
            // Season/encounter type are Malifaux strategy-pool concepts; TOS uses Operations (Phase 4).
            'uses_season' => false,
            'uses_encounter_type' => false,
        ],
    ],
];
