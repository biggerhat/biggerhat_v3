<?php

namespace App\Http\Controllers;

use App\Models\TabletopLayout;
use App\Support\Tabletop\Layouts;
use App\Support\Tabletop\TabletopAccess;
use App\Support\Tabletop\TabletopImages;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * The Tabletop landing page: the viewer's saved tables, the public gallery of tables other people have made, and the way into the sandbox.
 */
class TabletopTablesController extends Controller
{
    public function __invoke(Request $request): Response|ResponseFactory
    {
        // Pre-release: hidden (404, not 403) unless the experiment-tabletop flag is on or the user holds the beta permission.
        abort_unless(TabletopAccess::allows($request->user()), 404);

        $search = trim((string) $request->query('search', ''));
        $gallery = TabletopLayout::query()
            ->with('user:id,name')
            ->where('is_public', true)
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%'))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (TabletopLayout $l) => Layouts::payload($l, $request->user()));

        return inertia('Tools/TabletopTables', [
            'mine' => Layouts::mine($request->user()),
            'gallery' => $gallery,
            'active_search' => $search !== '' ? $search : null,
            'table_images' => TabletopImages::tables(),
            'terrain_images' => TabletopImages::terrain(),
        ]);
    }
}
