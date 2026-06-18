<?php

namespace App\Http\Controllers\API\V1\TOS;

use App\Http\Controllers\Controller;
use App\Models\TOS\Garrison;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags TOS Garrisons
 */
class GarrisonController extends Controller
{
    /**
     * List public TOS Garrisons
     *
     * Returns a paginated list of public Garrisons — the tournament pool a
     * player declares before an event — newest first.
     *
     * @queryParam search string Filter by garrison name. Example: Round 1 Pool
     * @queryParam allegiance string Filter by allegiance slug. Example: kings_empire
     * @queryParam format string Filter by format enum value. Example: standard
     * @queryParam per_page int Results per page (max 50). Example: 15
     */
    public function index(Request $request): JsonResponse
    {
        $query = Garrison::query()
            ->where('is_public', true)
            ->with(['user:id,name', 'allegiance:id,name,slug'])
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'LIKE', '%'.$request->get('search').'%'))
            ->when($request->filled('allegiance'), fn ($q) => $q->whereHas(
                'allegiance',
                fn ($a) => $a->where('slug', $request->get('allegiance'))
            ))
            ->when($request->filled('format'), fn ($q) => $q->where('format', $request->get('format')));

        $perPage = min((int) $request->get('per_page', 15), 50);
        $garrisons = $query->latest('updated_at')->paginate($perPage);

        return response()->json($garrisons->through(fn (Garrison $g) => [
            'name' => $g->name,
            'slug' => $g->slug,
            'share_code' => $g->share_code,
            'allegiance' => $g->allegiance->name,
            'format' => $g->format->value,
            'scrip_budget' => $g->scripBudget(),
            'scrip_spent' => $g->scripSpent(),
            'user' => $g->user->name,
            'updated_at' => $g->updated_at?->toISOString(),
            'url' => route('tos.garrisons.shared', $g->share_code),
        ]));
    }

    /**
     * View a public TOS Garrison by share code
     *
     * Returns the declared pool — units, the Asset pool (with quantities),
     * Stratagems, Envoys, the format budget, and whether it's currently legal.
     */
    public function show(string $shareCode): JsonResponse
    {
        $garrison = Garrison::query()
            ->where('share_code', $shareCode)
            ->where('is_public', true)
            ->with([
                'user:id,name',
                'allegiance:id,name,slug',
                'garrisonUnits.unit:id,name,scrip',
                'assets:id,name,scrip_cost',
                'stratagems:id,name',
                'envoys:id,name',
            ])
            ->firstOrFail();

        return response()->json([
            'name' => $garrison->name,
            'slug' => $garrison->slug,
            'share_code' => $garrison->share_code,
            'format' => $garrison->format->value,
            'allegiance' => $garrison->allegiance ? [
                'name' => $garrison->allegiance->name,
                'slug' => $garrison->allegiance->slug,
            ] : null,
            'scrip_budget' => $garrison->scripBudget(),
            'scrip_spent' => $garrison->scripSpent(),
            'scrip_remaining' => $garrison->scripRemaining(),
            'is_legal' => $garrison->isLegal(),
            'units' => $garrison->garrisonUnits->map(fn ($gu) => [
                'name' => $gu->unit->name,
                'scrip' => $gu->unit->scrip,
                'is_commander' => (bool) $gu->is_commander,
            ])->values()->all(),
            'assets' => $garrison->assets->map(fn ($a) => [
                'name' => $a->name,
                'scrip_cost' => $a->scrip_cost,
                'quantity' => (int) ($a->pivot->quantity ?? 1),
            ])->values()->all(),
            'stratagems' => $garrison->stratagems->pluck('name')->values()->all(),
            'envoys' => $garrison->envoys->pluck('name')->values()->all(),
            'user' => $garrison->user->name,
            'url' => route('tos.garrisons.shared', $garrison->share_code),
        ]);
    }
}
