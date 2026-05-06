<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Filtered random-character picker. Same vibe as the dice icon in the header
 * (which pulls anything at all), but lets a user narrow the pool first by
 * faction / keyword / characteristic / cost band.
 *
 * The form lives client-side so users can re-roll without bouncing through
 * Inertia visits; the actual pick happens here on demand via a query string.
 * `?roll=1` triggers the random selection so SEO crawlers land on a stable
 * empty state instead of bouncing characters in the page title.
 */
class RandomCharacterController extends Controller
{
    public function __invoke(Request $request): Response|ResponseFactory
    {
        $factions = collect(FactionEnum::cases())
            ->map(fn (FactionEnum $f) => [
                'value' => $f->value,
                'name' => $f->label(),
                'logo' => $f->logo(),
            ])
            ->values();

        // Keyword + Characteristic options used to populate the multiselects.
        // Keep these to slug/name pairs so the response stays small — the
        // actual Character model relations resolve those slugs server-side.
        $keywords = Keyword::orderBy('name')->get(['id', 'name', 'slug'])
            ->map(fn (Keyword $k) => ['value' => $k->slug, 'name' => $k->name])->values();

        $characteristics = Characteristic::orderBy('name')->get(['id', 'name', 'slug'])
            ->map(fn (Characteristic $c) => ['value' => $c->slug, 'name' => $c->name])->values();

        $picked = null;
        if ($request->boolean('roll')) {
            $picked = $this->roll($request);
        }

        return inertia('Tools/RandomCharacter', [
            'factions' => $factions,
            'keywords' => $keywords,
            'characteristics' => $characteristics,
            'filters' => [
                'faction' => $this->csv($request->get('faction')),
                'keyword' => $this->csv($request->get('keyword')),
                'characteristic' => $this->csv($request->get('characteristic')),
                'cost_min' => $request->filled('cost_min') ? (int) $request->get('cost_min') : null,
                'cost_max' => $request->filled('cost_max') ? (int) $request->get('cost_max') : null,
            ],
            'picked' => $picked,
        ]);
    }

    /**
     * Apply the same filters Advanced Search uses, then `inRandomOrder()->first()`.
     * Returns a slim payload tuned for the result card (we don't need every
     * relation here — the user clicks through to the canonical character page
     * for the full breakdown).
     */
    private function roll(Request $request): ?array
    {
        // Pull both the full miniatures list (so we still find a sculpt for
        // characters that only have promo art) and the standard subset, which
        // we prefer for the result card. Filter on `whereHas` so the picker
        // never lands on a character with zero rendered sculpts.
        $query = Character::standard()
            ->where('is_hidden', false)
            ->with(['miniatures' => fn ($q) => $q->orderBy('id'), 'standardMiniatures'])
            ->whereHas('miniatures');

        if ($factions = $this->csv($request->get('faction'))) {
            $query->whereIn('faction', $factions);
        }

        if ($keywords = $this->csv($request->get('keyword'))) {
            $query->whereHas('keywords', fn ($q) => $q->whereIn('keywords.slug', $keywords));
        }

        if ($characteristics = $this->csv($request->get('characteristic'))) {
            $query->whereHas('characteristics', fn ($q) => $q->whereIn('characteristics.slug', $characteristics));
        }

        if ($request->filled('cost_min')) {
            $query->where('cost', '>=', (int) $request->get('cost_min'));
        }
        if ($request->filled('cost_max')) {
            $query->where('cost', '<=', (int) $request->get('cost_max'));
        }

        $character = $query->inRandomOrder()->first();
        if (! $character) {
            return null;
        }

        // Prefer a random standard-edition sculpt — alts and promos can be
        // visually surprising for a "random character" pick. Fall back to any
        // miniature for characters that only have promo art on file.
        $pool = $character->standardMiniatures->isNotEmpty()
            ? $character->standardMiniatures
            : $character->miniatures;
        $miniature = $pool->random();

        // Shape the payload for CharacterCardView — that component owns the
        // flip / fullscreen / collection / wishlist / "View Character Page"
        // affordances, so we just feed it the miniature plus the parent
        // character slug. Drop the redundant metadata badges; users get the
        // full breakdown by clicking through.
        return [
            'id' => $character->id,
            'slug' => $character->slug,
            'display_name' => $character->display_name,
            'miniature' => $miniature ? [
                'id' => $miniature->id,
                'slug' => $miniature->slug,
                'display_name' => $character->display_name,
                'character_id' => $character->id,
                'front_image' => $miniature->front_image,
                'back_image' => $miniature->back_image,
            ] : null,
        ];
    }

    /**
     * Normalize a comma-or-array param into a clean string array. Lets the
     * front-end serialize multi-selects as `?faction=arcanists,bayou` (URL-share-
     * friendly) without forcing query repetition (`faction[]=...`).
     *
     * @return array<int, string>
     */
    private function csv(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), fn ($v) => $v !== ''));
        }
        if (! is_string($value) || $value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
