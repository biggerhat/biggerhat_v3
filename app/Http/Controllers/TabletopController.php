<?php

namespace App\Http\Controllers;

use App\Support\Tabletop\MarkerCatalogue;
use App\Support\Tabletop\TabletopAccess;
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

        return inertia('Tools/Tabletop', ['markers' => MarkerCatalogue::options(), 'token_options' => TokenCatalogue::options()]);
    }
}
