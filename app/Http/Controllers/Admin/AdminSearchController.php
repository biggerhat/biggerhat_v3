<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Blueprint;
use App\Models\Channel;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use App\Models\Lore;
use App\Models\Marker;
use App\Models\Miniature;
use App\Models\Package;
use App\Models\PodLink;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Token;
use App\Models\TOS\Ability as TosAbility;
use App\Models\TOS\Allegiance as TosAllegiance;
use App\Models\TOS\Stratagem as TosStratagem;
use App\Models\TOS\Unit as TosUnit;
use App\Models\Trigger;
use App\Models\Upgrade;
use App\Models\User;
use App\Support\AdminNav;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Record lookup behind the admin Cmd-K palette. Each searchable type is gated
 * by the same permission as its edit page, so a result is never a link the
 * user would be bounced from.
 */
class AdminSearchController extends Controller
{
    private const MIN_QUERY_LENGTH = 2;

    private const PER_TYPE = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < self::MIN_QUERY_LENGTH) {
            return response()->json(['results' => []]);
        }

        // Escape LIKE wildcards so "100%" or "a_b" match literally. An explicit
        // ESCAPE char is needed: SQLite has no default one (MySQL's is a backslash).
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
        $user = $request->user();

        $results = [];
        foreach ($this->sources() as $source) {
            if (! AdminNav::allows($user, $source['permission'])) {
                continue;
            }

            /** @var Builder<\Illuminate\Database\Eloquent\Model> $query */
            $query = ($source['query'] ?? fn () => $source['model']::query())();
            $records = $query
                ->where(function (Builder $q) use ($source, $like) {
                    foreach ($source['columns'] as $column) {
                        $q->orWhereRaw($q->getQuery()->getGrammar()->wrap($column)." like ? escape '!'", [$like]);
                    }
                })
                ->orderBy($source['columns'][0])
                ->limit(self::PER_TYPE)
                ->get();

            foreach ($records as $record) {
                $results[] = [
                    'type' => $source['type'],
                    'label' => (string) $record->{$source['columns'][0]},
                    'url' => route($source['route'], $record),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    /**
     * @return array<int, array{type: string, model: class-string, columns: array<int, string>, route: string, permission: string, query?: \Closure}>
     */
    private function sources(): array
    {
        return [
            ['type' => 'Character', 'model' => Character::class, 'columns' => ['display_name'], 'route' => 'admin.characters.edit', 'permission' => 'view_character'],
            ['type' => 'Miniature', 'model' => Miniature::class, 'columns' => ['display_name'], 'route' => 'admin.miniatures.edit', 'permission' => 'view_miniature'],
            ['type' => 'Upgrade', 'model' => Upgrade::class, 'columns' => ['name'], 'route' => 'admin.upgrades.edit', 'permission' => 'view_upgrade', 'query' => fn () => Upgrade::forCharacters()],
            ['type' => 'Crew Card', 'model' => Upgrade::class, 'columns' => ['name'], 'route' => 'admin.crews.edit', 'permission' => 'view_crew', 'query' => fn () => Upgrade::forCrews()],
            ['type' => 'Action', 'model' => Action::class, 'columns' => ['name'], 'route' => 'admin.actions.edit', 'permission' => 'view_action'],
            ['type' => 'Ability', 'model' => Ability::class, 'columns' => ['name'], 'route' => 'admin.abilities.edit', 'permission' => 'view_ability'],
            ['type' => 'Trigger', 'model' => Trigger::class, 'columns' => ['name'], 'route' => 'admin.triggers.edit', 'permission' => 'view_trigger'],
            ['type' => 'Keyword', 'model' => Keyword::class, 'columns' => ['name'], 'route' => 'admin.keywords.edit', 'permission' => 'view_keyword'],
            ['type' => 'Characteristic', 'model' => Characteristic::class, 'columns' => ['name'], 'route' => 'admin.characteristics.edit', 'permission' => 'view_characteristic'],
            ['type' => 'Token', 'model' => Token::class, 'columns' => ['name'], 'route' => 'admin.tokens.edit', 'permission' => 'view_token'],
            ['type' => 'Marker', 'model' => Marker::class, 'columns' => ['name'], 'route' => 'admin.markers.edit', 'permission' => 'view_marker'],
            ['type' => 'Scheme', 'model' => Scheme::class, 'columns' => ['name'], 'route' => 'admin.schemes.edit', 'permission' => 'view_scheme'],
            ['type' => 'Strategy', 'model' => Strategy::class, 'columns' => ['name'], 'route' => 'admin.strategies.edit', 'permission' => 'view_strategy'],
            ['type' => 'Package', 'model' => Package::class, 'columns' => ['name'], 'route' => 'admin.packages.edit', 'permission' => 'view_package'],
            ['type' => 'Lore', 'model' => Lore::class, 'columns' => ['name'], 'route' => 'admin.lores.edit', 'permission' => 'view_lore'],
            ['type' => 'Blueprint', 'model' => Blueprint::class, 'columns' => ['name'], 'route' => 'admin.blueprints.edit', 'permission' => 'view_blueprint'],
            ['type' => 'Channel', 'model' => Channel::class, 'columns' => ['name'], 'route' => 'admin.channels.edit', 'permission' => 'view_channel'],
            ['type' => 'POD Link', 'model' => PodLink::class, 'columns' => ['name'], 'route' => 'admin.pod_links.edit', 'permission' => 'view_pod_link'],
            ['type' => 'User', 'model' => User::class, 'columns' => ['name', 'email'], 'route' => 'admin.users.edit', 'permission' => 'view_user'],
            ['type' => 'TOS Unit', 'model' => TosUnit::class, 'columns' => ['name'], 'route' => 'admin.tos.units.edit', 'permission' => 'view_tos_unit'],
            ['type' => 'TOS Ability', 'model' => TosAbility::class, 'columns' => ['name'], 'route' => 'admin.tos.abilities.edit', 'permission' => 'view_tos_ability'],
            ['type' => 'TOS Allegiance', 'model' => TosAllegiance::class, 'columns' => ['name'], 'route' => 'admin.tos.allegiances.edit', 'permission' => 'view_tos_allegiance'],
            ['type' => 'TOS Stratagem', 'model' => TosStratagem::class, 'columns' => ['name'], 'route' => 'admin.tos.stratagems.edit', 'permission' => 'view_tos_stratagem'],
        ];
    }
}
