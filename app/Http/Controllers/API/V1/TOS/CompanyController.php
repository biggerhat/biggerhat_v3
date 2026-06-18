<?php

namespace App\Http\Controllers\API\V1\TOS;

use App\Http\Controllers\Controller;
use App\Models\TOS\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags TOS Companies
 */
class CompanyController extends Controller
{
    /**
     * List public TOS Companies
     *
     * Returns a paginated list of public Company army lists (the TOS analog of
     * Malifaux crew builds), newest first.
     *
     * @queryParam search string Filter by company name. Example: King's Hand
     * @queryParam allegiance string Filter by allegiance slug. Example: kings_empire
     * @queryParam per_page int Results per page (max 50). Example: 15
     */
    public function index(Request $request): JsonResponse
    {
        $query = Company::query()
            ->where('is_public', true)
            ->with([
                'user:id,name',
                'allegiance:id,name,slug',
                'companyUnits.unit:id,scrip',
            ])
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'LIKE', '%'.$request->get('search').'%'))
            ->when($request->filled('allegiance'), fn ($q) => $q->whereHas(
                'allegiance',
                fn ($a) => $a->where('slug', $request->get('allegiance'))
            ));

        $perPage = min((int) $request->get('per_page', 15), 50);
        $companies = $query->latest('updated_at')->paginate($perPage);

        return response()->json($companies->through(fn (Company $c) => [
            'name' => $c->name,
            'slug' => $c->slug,
            'share_code' => $c->share_code,
            'allegiance' => $c->allegiance->name,
            'unit_count' => $c->companyUnits->count(),
            'scrip_budget' => $c->scripBudget(),
            'scrip_spent' => $c->scripSpent(),
            'user' => $c->user->name,
            'updated_at' => $c->updated_at?->toISOString(),
            'url' => route('tos.companies.shared', $c->share_code),
        ]));
    }

    /**
     * View a public TOS Company by share code
     *
     * Returns the full roster — units (with attached Assets), the Commander, and
     * the Scrip budget/spent/remaining (rulebook p. 9).
     */
    public function show(string $shareCode): JsonResponse
    {
        $company = Company::query()
            ->where('share_code', $shareCode)
            ->where('is_public', true)
            ->with([
                'user:id,name',
                'allegiance:id,name,slug',
                'companyUnits.unit:id,name,scrip',
                'companyUnits.assets:id,name,scrip_cost',
            ])
            ->firstOrFail();

        return response()->json([
            'name' => $company->name,
            'slug' => $company->slug,
            'share_code' => $company->share_code,
            'allegiance' => $company->allegiance ? [
                'name' => $company->allegiance->name,
                'slug' => $company->allegiance->slug,
            ] : null,
            'scrip_budget' => $company->scripBudget(),
            'scrip_spent' => $company->scripSpent(),
            'scrip_remaining' => $company->scripRemaining(),
            'has_commander' => $company->hasCommander(),
            'units' => $company->companyUnits->map(fn ($cu) => [
                'name' => $cu->unit->name,
                'scrip' => $cu->unit->scrip,
                'is_commander' => (bool) $cu->is_commander,
                'assets' => $cu->assets->map(fn ($a) => [
                    'name' => $a->name,
                    'scrip_cost' => $a->scrip_cost,
                ])->values()->all(),
            ])->values()->all(),
            'user' => $company->user->name,
            'url' => route('tos.companies.shared', $company->share_code),
        ]);
    }
}
