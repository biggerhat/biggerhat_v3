<?php

namespace Database\Seeders;

use App\Models\Ability;
use App\Models\Action;
use App\Models\LootCard;
use App\Models\Trigger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seed the rules text + linked abilities/actions/triggers for each Bonanza
 * Loot Card side.
 *
 * Data format (see {@see effects()}):
 *
 *     'five-of-crows' => [
 *         'a' => [
 *             'title'  => 'Pact with the Damned',
 *             'effect' => 'This model gains the following abilities: Nefarious Pact, Arcane Reservoir',
 *
 *             // Optional — additive. The seeder also auto-detects names
 *             // mentioned in `effect`, so most cards only need these
 *             // fields for entities NOT referenced in the prose
 *             // (e.g. a signature attack you forgot to name).
 *             'abilities' => ['Some Ability'],
 *             'actions'   => ['Some Action'],
 *             'triggers'  => ['Some Trigger'],
 *
 *             // Flag a referenced action as the card's Signature when it
 *             // gets attached (sets is_signature_action on the pivot).
 *             'signature_actions' => ['Some Action'],
 *         ],
 *         'b' => [...],
 *     ],
 *
 * Idempotent — re-running detaches existing pivots for each side first via
 * the LootCard sync helpers, so the data file is the source of truth.
 *
 * Depends on: LootCardSeeder (rows must exist), plus whichever Ability /
 * Action / Trigger seeders populate the records referenced by name.
 */
class LootCardEffectsSeeder extends Seeder
{
    public function run(): void
    {
        // Pre-load name → record maps so each card pass doesn't requery.
        // Multiple Ability rows can share a name (different characters
        // each get their own copy in this codebase) — first hit wins;
        // any disambiguation lives in the data file via explicit lists.
        $abilities = Ability::all()->keyBy(fn (Ability $a) => mb_strtolower($a->name));
        $actions = Action::all()->keyBy(fn (Action $a) => mb_strtolower($a->name));
        $triggers = Trigger::all()->keyBy(fn (Trigger $t) => mb_strtolower($t->name));

        foreach ($this->effects() as $slug => $sides) {
            /** @var LootCard|null $card */
            $card = LootCard::where('slug', $slug)->first();
            if ($card === null) {
                $this->command?->warn("LootCardEffectsSeeder: '{$slug}' not found — run LootCardSeeder first.");

                continue;
            }

            foreach (['a', 'b'] as $side) {
                if (! array_key_exists($side, $sides)) {
                    continue;
                }
                $data = $sides[$side] ?? [];

                $card->{"title_{$side}"} = $data['title'] ?? null;
                $card->{"effect_{$side}"} = $data['effect'] ?? null;
                $card->save();

                $text = (string) ($data['effect'] ?? '');
                $attachedAbilities = $this->autoMatch($text, $abilities);
                $attachedActions = $this->autoMatch($text, $actions);
                $attachedTriggers = $this->autoMatch($text, $triggers);

                // Explicit additions — preserves prose-order of auto-matches,
                // appends any explicitly-named entries that weren't already
                // picked up.
                foreach ((array) ($data['abilities'] ?? []) as $name) {
                    if ($a = $abilities->get(mb_strtolower((string) $name))) {
                        $attachedAbilities[$a->id] = $a;
                    }
                }
                foreach ((array) ($data['actions'] ?? []) as $name) {
                    if ($a = $actions->get(mb_strtolower((string) $name))) {
                        $attachedActions[$a->id] = $a;
                    }
                }
                foreach ((array) ($data['triggers'] ?? []) as $name) {
                    if ($t = $triggers->get(mb_strtolower((string) $name))) {
                        $attachedTriggers[$t->id] = $t;
                    }
                }

                $signatures = collect((array) ($data['signature_actions'] ?? []))
                    ->map(fn ($n) => mb_strtolower((string) $n))
                    ->flip();

                $card->syncSideAbilities($side, array_keys($attachedAbilities));
                $card->syncSideTriggers($side, array_keys($attachedTriggers));
                $card->syncSideActions(
                    $side,
                    array_map(
                        fn (Action $a) => [
                            'action_id' => $a->id,
                            'is_signature_action' => $signatures->has(mb_strtolower($a->name)),
                        ],
                        array_values($attachedActions),
                    ),
                );
            }
        }
    }

    /**
     * Find every name in $map that appears verbatim in $text, returning
     * an id-keyed array in the order the names appear in the text.
     *
     * Built as one alternation regex sorted longest-first so that a name
     * like "Arcane Reservoir" wins over a shorter conflicting "Arcane",
     * and the regex engine skips past the longer match's characters
     * before retrying — no risk of double-attaching a substring.
     *
     * @param  Collection<string, Ability|Action|Trigger>  $map  lowercase-name keyed
     * @return array<int, Ability|Action|Trigger>
     */
    private function autoMatch(string $text, Collection $map): array
    {
        if ($text === '' || $map->isEmpty()) {
            return [];
        }

        $names = $map->keys()
            ->sortByDesc(fn (string $n) => mb_strlen($n))
            ->values()
            ->all();

        $pattern = '/('.implode('|', array_map(fn (string $n) => preg_quote($n, '/'), $names)).')/iu';

        if (! preg_match_all($pattern, $text, $found)) {
            return [];
        }

        $matches = [];
        foreach ($found[0] as $matchedName) {
            $entity = $map->get(mb_strtolower($matchedName));
            if ($entity !== null) {
                $matches[$entity->id] = $entity;
            }
        }

        return $matches;
    }

    /**
     * Loot card data, keyed by card slug. Add a card row here and the
     * seeder will populate `title_a`/`title_b`/`effect_a`/`effect_b`,
     * auto-link any Ability/Action/Trigger whose name appears in the
     * prose, and respect optional explicit attachment + signature lists.
     *
     * `protected` so tests can stub a subset of cards without mutating
     * the production data array.
     *
     * @return array<string, array{a?: array<string, mixed>, b?: array<string, mixed>}>
     */
    protected function effects(): array
    {
        return [
            // Example — replace with the actual canonical text from the
            // Wyrd doc as it's transcribed.
            'five-of-crows' => [
                'a' => [
                    'title' => 'Pact with the Damned',
                    'effect' => 'This model gains the following abilities: Nefarious Pact, Arcane Reservoir',
                ],
            ],
        ];
    }
}
