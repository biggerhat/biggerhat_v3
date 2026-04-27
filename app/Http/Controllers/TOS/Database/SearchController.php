<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Models\TOS\Asset;
use App\Models\TOS\Envoy;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Stratagem;
use App\Models\TOS\Unit;
use Illuminate\Http\Request;

/**
 * Unified TOS search across every entity type the database browser exposes.
 *
 * Deliberately lighter than the Malifaux `Database\SearchController`: no
 * field-syntax composable (`useSearchSyntax`), no saved searches, no CSV
 * export. The plan called those out as the L-effort piece — defer until
 * usage data shows demand.
 *
 * Each entity type contributes a normalized `{type, slug, name, snippet, url}`
 * row so the frontend can render a single mixed list grouped by type.
 */
class SearchController extends Controller
{
    private const TYPES = [
        'units', 'allegiances', 'allegiance_cards', 'envoys',
        'abilities', 'actions', 'special_rules', 'assets', 'stratagems',
    ];

    public function index(Request $request)
    {
        // `name_search` matches the rest of the TOS public index pages
        // (set by useListFiltering); accept legacy `q` for back-compat with
        // any external bookmarks.
        $q = trim((string) ($request->get('name_search') ?? $request->get('q', '')));
        $rawTypes = $request->get('types');
        $selectedTypes = is_array($rawTypes) ? $rawTypes : (is_string($rawTypes) ? explode(',', $rawTypes) : []);
        $selectedTypes = array_values(array_intersect(self::TYPES, $selectedTypes));
        if (empty($selectedTypes)) {
            $selectedTypes = self::TYPES;
        }

        $results = collect();

        if (strlen($q) >= 2) {
            $like = "%{$q}%";

            if (in_array('units', $selectedTypes, true)) {
                Unit::query()
                    ->where(fn ($qq) => $qq->where('name', 'LIKE', $like)->orWhere('title', 'LIKE', $like)->orWhere('description', 'LIKE', $like))
                    ->with(['sculpts:id,unit_id,slug', 'allegiances:id,name'])
                    ->limit(15)->get(['id', 'slug', 'name', 'title', 'description'])
                    ->each(function (Unit $u) use (&$results) {
                        $sculptSlug = $u->sculpts->first()?->slug;
                        $results->push([
                            'type' => 'units',
                            'type_label' => 'Unit',
                            'slug' => $u->slug,
                            'name' => $u->name.($u->title ? ", {$u->title}" : ''),
                            'snippet' => $u->description ? $this->snippet($u->description) : ($u->allegiances->pluck('name')->implode(', ') ?: null),
                            'url' => $sculptSlug ? route('tos.units.view', $sculptSlug) : null,
                        ]);
                    });
            }

            if (in_array('allegiances', $selectedTypes, true)) {
                Allegiance::query()
                    ->where(fn ($qq) => $qq->where('name', 'LIKE', $like)->orWhere('description', 'LIKE', $like))
                    ->limit(10)->get(['id', 'slug', 'name', 'description'])
                    ->each(fn (Allegiance $a) => $results->push([
                        'type' => 'allegiances',
                        'type_label' => 'Allegiance',
                        'slug' => $a->slug,
                        'name' => $a->name,
                        'snippet' => $a->description ? $this->snippet($a->description) : null,
                        'url' => route('tos.allegiances.view', $a->slug),
                    ]));
            }

            if (in_array('allegiance_cards', $selectedTypes, true)) {
                AllegianceCard::query()
                    ->where(fn ($qq) => $qq->where('name', 'LIKE', $like)->orWhere('body', 'LIKE', $like))
                    ->limit(10)->get(['id', 'slug', 'name', 'body'])
                    ->each(fn (AllegianceCard $c) => $results->push([
                        'type' => 'allegiance_cards',
                        'type_label' => 'Allegiance Card',
                        'slug' => $c->slug,
                        'name' => $c->name,
                        'snippet' => $c->body ? $this->snippet($c->body) : null,
                        'url' => route('tos.allegiance_cards.view', $c->slug),
                    ]));
            }

            if (in_array('envoys', $selectedTypes, true)) {
                Envoy::query()
                    ->where(fn ($qq) => $qq->where('name', 'LIKE', $like)->orWhere('body', 'LIKE', $like))
                    ->limit(10)->get(['id', 'slug', 'name', 'body'])
                    ->each(fn (Envoy $e) => $results->push([
                        'type' => 'envoys',
                        'type_label' => 'Envoy',
                        'slug' => $e->slug,
                        'name' => $e->name,
                        'snippet' => $e->body ? $this->snippet($e->body) : null,
                        'url' => route('tos.envoys.view', $e->slug),
                    ]));
            }

            // Abilities, actions, and special rules don't have per-entity
            // public view pages — they're visible only on their list pages.
            // Link to those list pages with name_search pre-filled so the
            // user lands in context.
            if (in_array('abilities', $selectedTypes, true)) {
                Ability::query()
                    ->where(fn ($qq) => $qq->where('name', 'LIKE', $like)->orWhere('body', 'LIKE', $like))
                    ->limit(10)->get(['id', 'slug', 'name', 'body'])
                    ->each(fn (Ability $a) => $results->push([
                        'type' => 'abilities',
                        'type_label' => 'Ability',
                        'slug' => $a->slug,
                        'name' => $a->name,
                        'snippet' => $a->body ? $this->snippet($a->body) : null,
                        'url' => route('tos.abilities.index', ['name_search' => $a->name]),
                    ]));
            }

            if (in_array('actions', $selectedTypes, true)) {
                Action::query()
                    ->where(fn ($qq) => $qq->where('name', 'LIKE', $like)->orWhere('body', 'LIKE', $like))
                    ->limit(10)->get(['id', 'slug', 'name', 'body'])
                    ->each(fn (Action $a) => $results->push([
                        'type' => 'actions',
                        'type_label' => 'Action',
                        'slug' => $a->slug,
                        'name' => $a->name,
                        'snippet' => $a->body ? $this->snippet($a->body) : null,
                        'url' => route('tos.actions.index', ['name_search' => $a->name]),
                    ]));
            }

            if (in_array('special_rules', $selectedTypes, true)) {
                SpecialUnitRule::query()
                    ->where(fn ($qq) => $qq->where('name', 'LIKE', $like)->orWhere('description', 'LIKE', $like))
                    ->limit(10)->get(['id', 'slug', 'name', 'description'])
                    ->each(fn (SpecialUnitRule $r) => $results->push([
                        'type' => 'special_rules',
                        'type_label' => 'Special Rule',
                        'slug' => $r->slug,
                        'name' => $r->name,
                        'snippet' => $r->description ? $this->snippet($r->description) : null,
                        'url' => route('tos.special_rules.index', ['name_search' => $r->name]),
                    ]));
            }

            if (in_array('assets', $selectedTypes, true)) {
                Asset::query()
                    ->where(fn ($qq) => $qq->where('name', 'LIKE', $like)->orWhere('body', 'LIKE', $like))
                    ->limit(10)->get(['id', 'slug', 'name', 'body'])
                    ->each(fn (Asset $a) => $results->push([
                        'type' => 'assets',
                        'type_label' => 'Asset',
                        'slug' => $a->slug,
                        'name' => $a->name,
                        'snippet' => $a->body ? $this->snippet($a->body) : null,
                        'url' => route('tos.assets.view', $a->slug),
                    ]));
            }

            if (in_array('stratagems', $selectedTypes, true)) {
                Stratagem::query()
                    ->where(fn ($qq) => $qq->where('name', 'LIKE', $like)->orWhere('effect', 'LIKE', $like))
                    ->limit(10)->get(['id', 'slug', 'name', 'effect'])
                    ->each(fn (Stratagem $s) => $results->push([
                        'type' => 'stratagems',
                        'type_label' => 'Stratagem',
                        'slug' => $s->slug,
                        'name' => $s->name,
                        'snippet' => $s->effect ? $this->snippet($s->effect) : null,
                        'url' => route('tos.stratagems.view', $s->slug),
                    ]));
            }
        }

        return inertia('TOS/Search/Index', [
            'name_search' => $q,
            'selected_types' => $selectedTypes,
            'all_types' => array_map(fn (string $t) => ['value' => $t, 'name' => ucwords(str_replace('_', ' ', $t))], self::TYPES),
            'results' => $results->values(),
        ]);
    }

    /**
     * Trim long bodies down to a search-result-card-sized snippet.
     */
    private function snippet(string $text, int $length = 140): string
    {
        $clean = trim(strip_tags(preg_replace('/{{\s*[\w_]+\s*}}/', '', $text) ?? ''));
        if (mb_strlen($clean) <= $length) {
            return $clean;
        }

        return mb_substr($clean, 0, $length).'…';
    }
}
