<?php

namespace App\Http\Controllers;

use Inertia\Response;
use Inertia\ResponseFactory;

/** The public roadmap page. Like the privacy page, its content lives in the front end (resources/js/lib/roadmap.ts) so it can be edited without touching PHP. */
class RoadmapController extends Controller
{
    public function show(): Response|ResponseFactory
    {
        return inertia('Roadmap');
    }
}
