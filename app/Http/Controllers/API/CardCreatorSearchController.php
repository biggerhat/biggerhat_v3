<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Keyword;
use App\Models\Trigger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardCreatorSearchController extends Controller
{
    public function actions(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $actions = Action::where('name', 'LIKE', "%{$q}%")
            ->with('triggers')
            ->limit(15)
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
            ->limit(15)
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
}
