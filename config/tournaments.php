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
            'game_size' => ['label' => 'Encounter Size (SS)', 'unit' => 'ss', 'default' => 50, 'min' => 20, 'max' => 100, 'step' => 5],
            'uses_season' => true,
            'uses_encounter_type' => true,
            // A Malifaux score is strategy VP (max 5) + scheme VP (max 6) = up to 11.
            'score_fields' => [
                ['key' => 'strategy', 'label' => 'Strategy VP', 'min' => 0, 'max' => 5],
                ['key' => 'scheme', 'label' => 'Scheme VP', 'min' => 0, 'max' => 6],
            ],
            // Each round needs a strategy, a deployment and a 3-scheme pool before it can be paired.
            'scenario_fields' => ['strategy', 'deployment', 'scheme_pool'],
            // Players declare one Malifaux faction for the whole event (Gaining Grounds is fixed-faction).
            'declaration' => 'faction',
            'requires_declaration' => true,
            'tracks_leaders' => true,
            'supports_tracker' => true,
            // Optional variants from the Gaining Grounds packet ("Optional Game Variants"). Crossroads, Team Event,
            // Double Rush and Total War are covered by the Encounter Type setting instead.
            'variants' => [
                ['key' => 'alternating_leaders', 'label' => 'Alternating Leaders', 'description' => 'Players may not hire a master that was hired in a previous round on the same day. Two masters with the same name and different titles count as different masters.'],
                ['key' => 'bans', 'label' => 'Bans', 'description' => 'After leaders are announced, starting with the attacker, each player may name up to X keywords not on their opponent\'s leader. The opponent may not hire models with those keywords unless they share a keyword with their leader or are versatile.', 'param' => ['label' => 'Keywords banned (X)', 'min' => 1, 'max' => 5, 'default' => 1]],
                ['key' => 'blind_battle', 'label' => 'Blind Battle', 'description' => 'Players do not reveal their faction or leader until the Reveal Crews step of the encounter.'],
                ['key' => 'fixed_leaders', 'label' => 'Fixed Leaders', 'description' => 'At check-in players name a model that can be a leader, and may only choose either title of that model as their leader.'],
                ['key' => 'fixed_pool', 'label' => 'Fixed Pool', 'description' => 'At check-in players provide a pool of models costing no more than X. Other than the leader (and its totems), they may only hire from that pool.', 'param' => ['label' => 'Pool cost (X)', 'min' => 10, 'max' => 150, 'default' => 60]],
                ['key' => 'fate_locked', 'label' => 'Fate-Locked', 'description' => 'The TO picks four schemes and an order. Each turn players may score the scheme whose order matches the turn number; players do not pick schemes as normal.'],
                ['key' => 'beyond_time_and_space', 'label' => 'Beyond Time and Space', 'description' => 'Players bring every Gaining Grounds scheme set released for fourth edition and shuffle them together to form the scheme pool; Next Available Scheme may use any printed copy.'],
                ['key' => 'wrench_in_the_plans', 'label' => 'Wrench in the Plans', 'description' => 'When picking new schemes, each player draws an additional random scheme, which may be selected in addition to any provided by the scheme completed or abandoned.'],
            ],
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
            'game_size' => ['label' => 'Game Size (Scrip)', 'unit' => 'Scrip', 'default' => 40, 'min' => 20, 'max' => 100, 'step' => 5],
            // Season/encounter type are Malifaux strategy-pool concepts; TOS uses Operations (Phase 4).
            'uses_season' => false,
            'uses_encounter_type' => false,
            // Fields of Glory records a single VP total per player.
            'score_fields' => [
                ['key' => 'vp', 'label' => 'Victory Points', 'min' => 0, 'max' => 30],
            ],
            // Each round picks an Operation and a Deployment from the TOS reference data.
            'scenario_fields' => ['tos_operation', 'tos_deployment'],
            // The Game Tracker is Malifaux-only.
            'supports_tracker' => false,
            // Players declare an Allegiance (fixed for every round) and may link their public Garrison.
            'declaration' => 'allegiance',
            'requires_declaration' => true,
            // Commanders come from the Garrison and aren't recorded per game.
            'tracks_leaders' => false,
            'variants' => [
                ['key' => 'close_objectives', 'label' => 'Close Objectives', 'description' => 'If an Action gained from an Operation would require a Fireteam to be within 3" and LoS of an Objective, the Fireteam must instead be within 1" and LoS of it.'],
                ['key' => 'strategic_alliance', 'label' => 'Strategic Alliance', 'description' => 'When creating a Stratagem Deck, players may include up to two Stratagems from an Allegiance of which they have an Envoy and a Commander. "Friendly unit/Asset/Fireteam" then only refers to those of that Stratagem\'s Allegiance.'],
                ['key' => 'decisive_victory', 'label' => 'Decisive Victory', 'description' => 'If, at the end of any turn after the second, a player\'s VP exceeds their opponent\'s by 12 or more, the game ends immediately.'],
            ],
        ],

        // A blank slate: pick the scoring, tiebreakers and rounds yourself ("Customize scoring" on the event).
        'custom' => [
            'label' => 'Custom — set your own scoring',
            'game_system' => 'custom',
            'scoring' => [
                'base_tp' => 0, 'win_tp' => 3, 'tie_tp' => 1, 'loss_tp' => 0,
                'diff_adds_to_tp' => false, 'diff_cap' => null,
                'bye_tp' => 3, 'bye_diff' => 0, 'bye_vp' => 0,
                'forfeit_vp' => 5, 'forfeit_loser_tp' => 0,
            ],
            'tiebreakers' => ['diff', 'vp'],
            'tiebreaker_toggle' => true,
            'suggested_rounds' => [[4, 15, 3], [16, 32, 4], [33, null, 5]],
            'default_round_time_limit' => 120,
            'game_size' => ['label' => 'Game Size', 'unit' => '', 'default' => 50, 'min' => 1, 'max' => 1000, 'step' => 1],
            'uses_season' => false,
            'uses_encounter_type' => false,
            'score_fields' => [
                ['key' => 'vp', 'label' => 'Victory Points', 'min' => 0, 'max' => 99],
            ],
            'scenario_fields' => [],
            'declaration' => 'none',
            'requires_declaration' => false,
            'tracks_leaders' => false,
            'supports_tracker' => false,
            'variants' => [],
        ],
    ],
];
