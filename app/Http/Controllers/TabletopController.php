<?php

namespace App\Http\Controllers;

use App\Models\TabletopLayout;
use App\Support\Tabletop\Layouts;
use App\Support\Tabletop\MarkerCatalogue;
use App\Support\Tabletop\TabletopAccess;
use App\Support\Tabletop\TabletopImages;
use App\Support\Tabletop\TokenCatalogue;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

class TabletopController extends Controller
{
    public function __invoke(Request $request): Response|ResponseFactory
    {
        // Pre-release: hidden (404, not 403) unless the experiment-tabletop flag is on or the user holds the beta permission.
        abort_unless(TabletopAccess::allows($request->user()), 404);

        $incoming = null;
        if ($request->filled('layout')) {
            // A layout someone shared by link (or one of the viewer's own). Anything else looks like it does not exist.
            $layout = TabletopLayout::with('user:id,name')->where('uuid', (string) $request->query('layout'))->first();
            abort_unless($layout && Layouts::visibleTo($layout, $request->user()), 404);
            $incoming = Layouts::payload($layout, $request->user());
        }

        return inertia('Tools/Tabletop', [
            'layouts' => Layouts::mine($request->user()),
            'incoming_layout' => $incoming,
            'markers' => MarkerCatalogue::options(),
            'token_options' => TokenCatalogue::options(),
            'table_images' => TabletopImages::tables(),
            'terrain_images' => TabletopImages::terrain(),
        ]);
    }
}
