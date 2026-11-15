<?php

namespace App\Http\Controllers;

use App\Support\Tabletop\MarkerCatalogue;
use Inertia\Response;
use Inertia\ResponseFactory;
use Laravel\Pennant\Feature;

class TabletopController extends Controller
{
    public function __invoke(): Response|ResponseFactory
    {
        // Pre-release: hidden (404, not 403) until the experiment-tabletop flag is switched on.
        abort_unless(Feature::for(null)->active('experiment-tabletop'), 404);

        return inertia('Tools/Tabletop', ['markers' => MarkerCatalogue::options()]);
    }
}
