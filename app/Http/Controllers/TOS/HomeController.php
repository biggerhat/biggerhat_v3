<?php

namespace App\Http\Controllers\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Models\TOS\Asset;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Stratagem;
use App\Models\TOS\Trigger;
use App\Models\TOS\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        // Pull every Main Allegiance once, then derive unit counts via a single
        // grouped query against the M:M pivot — keeps the page render at a
        // fixed 4 queries (allegiances + units pivot + syndicates + stats)
        // regardless of how many Allegiances exist.
        $allegiances = Allegiance::query()
            ->mainAllegiances()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $unitCounts = DB::table('tos_allegiance_unit')
            ->select('allegiance_id', DB::raw('COUNT(*) as total'))
            ->groupBy('allegiance_id')
            ->pluck('total', 'allegiance_id');

        $allegiances->each(fn ($a) => $a->setAttribute('unit_count', (int) ($unitCounts[$a->id] ?? 0)));

        $syndicates = Allegiance::query()
            ->syndicates()
            ->orderBy('name')
            ->get();
        $syndicates->each(fn ($s) => $s->setAttribute('unit_count', (int) ($unitCounts[$s->id] ?? 0)));

        return inertia('TOS/Index', [
            'allegiances' => $allegiances,
            'syndicates' => $syndicates,
            'stats' => [
                'units' => Unit::query()->notCombinedArmsChild()->count(),
                'allegiances' => Allegiance::query()->mainAllegiances()->count(),
                'syndicates' => Allegiance::query()->syndicates()->count(),
                'allegiance_cards' => AllegianceCard::count(),
                'assets' => Asset::count(),
                'stratagems' => Stratagem::count(),
                'abilities' => Ability::count(),
                'actions' => Action::count(),
                'triggers' => Trigger::count(),
                'special_rules' => SpecialUnitRule::count(),
            ],
            'type_pool_counts' => [
                'earth' => $this->typePoolCount(AllegianceTypeEnum::Earth),
                'malifaux' => $this->typePoolCount(AllegianceTypeEnum::Malifaux),
            ],
        ]);
    }

    /**
     * Count of top-level units that hire into ANY Allegiance of the given
     * type — direct M:M attachment OR matching `restriction` Neutral pool.
     * Mirrors `Unit::scopeHireableInto` for an arbitrary type rather than a
     * specific Allegiance.
     */
    private function typePoolCount(AllegianceTypeEnum $type): int
    {
        return Unit::query()
            ->notCombinedArmsChild()
            ->where(function (Builder $q) use ($type) {
                $q->whereHas('allegiances', fn (Builder $inner) => $inner
                    ->where('tos_allegiances.type', $type->value)
                    ->orWhere('tos_allegiances.secondary_type', $type->value))
                    ->orWhere('restriction', $type->value);
            })
            ->count();
    }
}
