<?php

namespace App\Http\Controllers\Database;

use App\Enums\SculptVersionEnum;
use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Character;
use Illuminate\Http\Request;

class BlueprintController extends Controller
{
    public function index(Request $request)
    {
        $query = Blueprint::withImage()
            ->with(['characters.standardMiniatures', 'miniatures', 'packages'])
            ->withCount(['characters', 'miniatures', 'packages'])
            ->orderBy('name', 'ASC');

        if ($request->filled('name_search')) {
            $query->where('name', 'LIKE', '%'.$request->get('name_search').'%');
        }

        if ($request->filled('sculpt_version')) {
            $query->where('sculpt_version', $request->get('sculpt_version'));
        }

        if ($request->filled('character')) {
            $query->whereHas('characters', function ($q) use ($request) {
                $q->where('characters.slug', $request->get('character'));
            });
        }

        if ($request->filled('package')) {
            $query->whereHas('packages', function ($q) use ($request) {
                $q->where('packages.slug', $request->get('package'));
            });
        }

        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        $blueprints = $query->paginate($perPage)->withQueryString();

        return inertia('Blueprints/Index', [
            'blueprints' => $blueprints,
            'result_count' => $blueprints->total(),
            'sculpt_versions' => fn () => SculptVersionEnum::toSelectOptions(),
            'characters' => fn () => Character::toSelectOptions('display_name', 'slug'),
        ]);
    }
}
