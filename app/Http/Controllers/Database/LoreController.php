<?php

namespace App\Http\Controllers\Database;

use App\Enums\LoreMediaTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Lore;
use App\Models\LoreMedia;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

class LoreController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        $query = Lore::with('media', 'characters.standardMiniatures');

        if ($request->get('name_search')) {
            $query->where('name', 'like', '%'.$request->get('name_search').'%');
        }

        if ($request->get('media_type')) {
            $query->whereHas('media', function ($q) use ($request) {
                $q->where('type', $request->get('media_type'));
            });
        }

        if ($request->get('lore_media')) {
            $query->whereHas('media', function ($q) use ($request) {
                $q->where('lore_media.id', $request->get('lore_media'));
            });
        }

        if ($request->get('character')) {
            $query->whereHas('characters', function ($q) use ($request) {
                $q->where('characters.slug', $request->get('character'));
            });
        }

        $lores = $query->orderBy('name', 'ASC')->paginate(30)->withQueryString();

        return inertia('Lore/Index', [
            'lores' => $lores,
            'result_count' => $lores->total(),
            'media_types' => LoreMediaTypeEnum::toSelectOptions(),
            'lore_media' => LoreMedia::orderBy('name')->get()->map(fn (LoreMedia $m) => [
                'name' => $m->name,
                'value' => (string) $m->id,
            ]),
            'characters' => fn () => Character::toSelectOptions('display_name', 'slug'),
        ]);
    }
}
