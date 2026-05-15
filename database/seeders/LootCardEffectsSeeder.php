<?php

namespace Database\Seeders;

use App\Models\Ability;
use App\Models\Action;
use App\Models\LootCard;
use App\Models\Trigger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Data format (see {@see effects()}):
 *
 *     'five-of-crows' => [
 *         'a' => [
 *             'title'  => 'Pact with the Damned',
 *             'effect' => 'This model gains the following abilities: Nefarious Pact, Arcane Reservoir',
 *             'abilities' => ['Some Ability'],            // optional, additive
 *             'actions'   => ['Some Action'],
 *             'triggers'  => ['Some Trigger'],
 *             'signature_actions' => ['Some Action'],     // pivot is_signature_action
 *         ],
 *         'b' => [...],
 *     ],
 */
class LootCardEffectsSeeder extends Seeder
{
    public function run(): void
    {
        // Name collisions (same Ability name across characters) resolve
        // first-hit-wins; disambiguate via explicit lists in the data file.
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
     * Longest-first alternation regex so "Arcane Reservoir" beats a
     * conflicting shorter "Arcane".
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
     * `protected` so tests can stub a subset of cards.
     *
     * @return array<string, array{a?: array<string, mixed>, b?: array<string, mixed>}>
     */
    protected function effects(): array
    {
        return [
            'five-of-crows' => [
                'a' => [
                    'title' => 'Pact with the Damned',
                    'effect' => 'This model gains the following abilities: Nefarious Pact, Arcane Reservoir',
                ],
            ],
        ];
    }
}
