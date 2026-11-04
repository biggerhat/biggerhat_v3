<?php

namespace App\Http\Controllers\API;

use App\Enums\ActionTypeEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use App\Models\Keyword;
use App\Models\Marker;
use App\Models\Token;
use App\Models\Trigger;
use App\Models\Upgrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CardCreatorSearchController extends Controller
{
    public function actions(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        // Tab filter for the browse modal (Attack/Tactical) — absent/invalid
        // values leave results unfiltered, matching the inline autocomplete's
        // existing behavior.
        $type = (string) $request->get('type', '');
        $typeFilter = in_array($type, [ActionTypeEnum::Attack->value, ActionTypeEnum::Tactical->value], true) ? $type : null;

        $actions = Action::where('name', 'LIKE', "%{$q}%")
            ->when($typeFilter !== null, fn ($qq) => $qq->where('type', $typeFilter))
            ->with('triggers')
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json($actions->map(fn (Action $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'type' => $a->type,
            'is_signature' => (bool) $a->is_signature,
            'stone_cost' => $a->stone_cost ?? 0,
            'range' => $a->range,
            'range_type' => $a->range_type,
            'stat' => $a->stat,
            'stat_suits' => $a->stat_suits,
            'stat_modifier' => $a->stat_modifier,
            'resisted_by' => $a->resisted_by,
            'target_number' => $a->target_number,
            'target_suits' => $a->target_suits,
            'damage' => $a->damage,
            'description' => $a->description,
            'source_id' => $a->id,
            'triggers' => $a->triggers->map(fn (Trigger $t) => [
                'name' => $t->name,
                'suits' => $t->suits,
                'stone_cost' => $t->stone_cost ?? 0,
                'description' => $t->description,
                'source_id' => $t->id,
            ])->values()->toArray(),
        ])->values());
    }

    public function abilities(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $abilities = Ability::where('name', 'LIKE', "%{$q}%")
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json($abilities->map(fn (Ability $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'suits' => $a->suits,
            'defensive_ability_type' => $a->defensive_ability_type,
            'costs_stone' => (bool) $a->costs_stone,
            'description' => $a->description,
            'source_id' => $a->id,
        ])->values());
    }

    public function triggers(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $triggers = Trigger::where('name', 'LIKE', "%{$q}%")
            ->limit(15)
            ->get();

        return response()->json($triggers->map(fn (Trigger $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'suits' => $t->suits,
            'stone_cost' => $t->stone_cost ?? 0,
            'description' => $t->description,
            'source_id' => $t->id,
        ])->values());
    }

    public function keywords(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $keywords = Keyword::where('name', 'LIKE', "%{$q}%")
            ->limit(15)
            ->get();

        return response()->json($keywords->map(fn (Keyword $k) => [
            'id' => $k->id,
            'name' => $k->name,
        ])->values());
    }

    public function crewUpgrades(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $results = collect();

        // Official crew upgrades
        $official = Upgrade::forCrews()
            ->where('name', 'LIKE', "%{$q}%")
            ->limit(10)
            ->get();

        foreach ($official as $u) {
            $results->push([
                'source_type' => 'official',
                'id' => $u->id,
                'name' => $u->name,
            ]);
        }

        // User's custom crew upgrades
        if (Auth::check()) {
            $custom = CustomUpgrade::where('user_id', Auth::id())
                ->where('domain', 'crew')
                ->where('name', 'LIKE', "%{$q}%")
                ->limit(5)
                ->get();

            foreach ($custom as $u) {
                $results->push([
                    'source_type' => 'custom',
                    'id' => $u->id,
                    'name' => $u->name,
                ]);
            }
        }

        return response()->json($results->values());
    }

    public function characters(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $results = collect();

        // Official characters
        $official = Character::where('display_name', 'LIKE', "%{$q}%")
            ->limit(10)
            ->get();

        foreach ($official as $c) {
            $results->push([
                'source_type' => 'official',
                'id' => $c->id,
                'name' => $c->display_name,
            ]);
        }

        // User's custom characters
        if (Auth::check()) {
            $custom = CustomCharacter::where('user_id', Auth::id())
                ->where('display_name', 'LIKE', "%{$q}%")
                ->limit(5)
                ->get();

            foreach ($custom as $c) {
                $results->push([
                    'source_type' => 'custom',
                    'id' => $c->id,
                    'name' => $c->display_name,
                    'station' => $c->getRawOriginal('station'),
                    'front_image' => $c->front_image,
                ]);
            }
        }

        return response()->json($results->values());
    }

    public function tokens(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $tokens = Token::where('name', 'LIKE', "%{$q}%")
            ->limit(15)
            ->get();

        return response()->json($tokens->map(fn (Token $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'description' => $t->description,
            'source_id' => $t->id,
        ])->values());
    }

    public function markers(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $markers = Marker::where('name', 'LIKE', "%{$q}%")
            ->limit(15)
            ->get();

        return response()->json($markers->map(fn (Marker $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'description' => $m->description,
            'source_id' => $m->id,
        ])->values());
    }

    public function characterDetail(Character $character): JsonResponse
    {
        $character->load(['keywords', 'abilities', 'actions.triggers', 'characteristics', 'crewUpgrades', 'totem']);

        return response()->json([
            'name' => $character->name,
            'title' => $character->title,
            'faction' => $character->faction->value,
            'second_faction' => $character->second_faction?->value,
            'station' => $character->station?->value,
            'cost' => $character->cost,
            'health' => $character->health,
            'defense' => $character->defense,
            'defense_suit' => $character->defense_suit?->value,
            'willpower' => $character->willpower,
            'willpower_suit' => $character->willpower_suit?->value,
            'speed' => $character->speed,
            'size' => $character->size,
            'base' => $character->base->value ?? '30',
            'count' => $character->count ?? 1,
            'summon_target_number' => $character->summon_target_number,
            'generates_stone' => (bool) $character->generates_stone,
            'is_unhirable' => (bool) $character->is_unhirable,
            'keywords' => $character->keywords->map(fn ($k) => ['id' => $k->id, 'name' => $k->name])->values(),
            'characteristics' => $character->characteristics->pluck('name')->values(),
            'abilities' => $character->abilities->map(fn (Ability $a) => $this->abilityPayload($a))->values(),
            'actions' => $character->actions->map(fn (Action $a) => $this->actionPayload($a))->values(),
            'linked_crew_upgrades' => $character->crewUpgrades->map(fn ($u) => [
                'source_type' => 'official',
                'id' => $u->id,
                'name' => $u->name,
            ])->values(),
            'linked_totems' => $character->totem ? [[
                'source_type' => 'official',
                'id' => $character->totem->id,
                'name' => $character->totem->display_name,
            ]] : [],
        ]);
    }

    /** Official standard upgrades by name, for the Duplicate-from-official picker in the upgrade editor. */
    public function upgrades(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $domain = $request->get('domain') === 'crew' ? UpgradeDomainTypeEnum::Crew : UpgradeDomainTypeEnum::Character;

        return response()->json(
            Upgrade::standard()
                ->where('domain', $domain->value)
                ->where('name', 'LIKE', "%{$q}%")
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name', 'type', 'faction'])
                ->map(fn (Upgrade $u) => ['id' => $u->id, 'name' => $u->name, 'type' => $u->type?->value, 'faction' => $u->faction?->value])
                ->values(),
        );
    }

    public function upgradeDetail(Upgrade $upgrade): JsonResponse
    {
        abort_unless($upgrade->game_mode_type === GameModeTypeEnum::Standard, 404);

        $upgrade->load(['abilities', 'actions.triggers', 'triggers', 'tokens', 'markers', 'keywords', 'masters']);

        // Official descriptions mark where each attached ability / action / trigger sits with `{{ability=}}`-style
        // placeholders. Walk the text and swap each one for the next attached item of that kind, so the copy keeps the
        // card's real order; anything the text never placed goes at the end.
        $queues = [
            'ability' => $upgrade->abilities->map(fn (Ability $a) => ['type' => 'ability', 'data' => $this->abilityPayload($a)])->all(),
            'action' => $upgrade->actions->map(fn (Action $a) => ['type' => 'action', 'data' => $this->actionPayload($a)])->all(),
            'trigger' => $upgrade->triggers->map(fn (Trigger $t) => ['type' => 'trigger', 'data' => [
                'name' => $t->name,
                'suits' => $t->suits,
                'stone_cost' => $t->stone_cost ?? 0,
                'description' => $t->description,
                'source_id' => $t->id,
            ]])->all(),
        ];

        $blocks = collect();
        $segments = preg_split('/(\{\{(?:ability|actioni?|trigger)=\}\})/', (string) $upgrade->description, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($segments as $segment) {
            if (preg_match('/^\{\{(ability|action|trigger)i?=\}\}$/', $segment, $m)) {
                if ($queues[$m[1]]) {
                    $blocks->push(array_shift($queues[$m[1]]));
                }
            } elseif (filled(trim($segment))) {
                $blocks->push(['type' => 'text', 'text' => trim($segment)]);
            }
        }
        foreach ($queues as $leftover) {
            foreach ($leftover as $block) {
                $blocks->push($block);
            }
        }

        return response()->json([
            'name' => $upgrade->name,
            'type' => $upgrade->type?->value,
            'faction' => $upgrade->faction?->value,
            'limitations' => $upgrade->limitations?->value,
            'plentiful' => $upgrade->plentiful,
            'master_name' => $upgrade->masters->first()?->display_name,
            'keyword_name' => $upgrade->keywords->first()?->name,
            'content_blocks' => $blocks->values(),
            'back_tokens' => $upgrade->tokens->map(fn (Token $t) => ['name' => $t->name, 'description' => $t->description, 'source_id' => $t->id])->values(),
            'back_markers' => $upgrade->markers->map(fn (Marker $m) => ['name' => $m->name, 'description' => $m->description, 'source_id' => $m->id])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function abilityPayload(Ability $a): array
    {
        return [
            'name' => $a->name,
            'suits' => $a->suits,
            'defensive_ability_type' => $a->defensive_ability_type,
            'costs_stone' => (bool) $a->costs_stone,
            'description' => $a->description,
            'source_id' => $a->id,
        ];
    }

    /** @return array<string, mixed> */
    private function actionPayload(Action $a): array
    {
        return [
            'name' => $a->name,
            'type' => $a->type,
            'is_signature' => (bool) $a->pivot->is_signature_action, // @phpstan-ignore-line property.notFound (pivot from MorphToMany)
            'stone_cost' => $a->stone_cost ?? 0,
            'range' => $a->range,
            'range_type' => $a->range_type,
            'stat' => $a->stat,
            'stat_suits' => $a->stat_suits,
            'stat_modifier' => $a->stat_modifier,
            'resisted_by' => $a->resisted_by,
            'target_number' => $a->target_number,
            'target_suits' => $a->target_suits,
            'damage' => $a->damage,
            'description' => $a->description,
            'source_id' => $a->id,
            'triggers' => $a->triggers->map(fn ($t) => [
                'name' => $t->name,
                'suits' => $t->suits,
                'stone_cost' => $t->stone_cost ?? 0,
                'description' => $t->description,
                'source_id' => $t->id,
            ])->values()->toArray(),
        ];
    }
}
