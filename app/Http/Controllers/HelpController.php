<?php

namespace App\Http\Controllers;

use App\Support\Help\HelpTopics;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

/** The help pages: an index of the features that have help, and one page per feature. The text is in resources/js/lib/help/topics. */
class HelpController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        return inertia('Help/Index', ['topics' => HelpTopics::visibleTo($request->user())]);
    }

    public function show(Request $request, string $topic): Response|ResponseFactory
    {
        abort_unless(HelpTopics::allows($topic, $request->user()), 404);

        return inertia('Help/Show', ['slug' => $topic]);
    }
}
