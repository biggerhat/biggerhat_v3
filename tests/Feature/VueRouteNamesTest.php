<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
 * A bad route() name in a Vue page throws a Ziggy "route not found" error that
 * crashes the whole render (SSR + client hydration) — a blank page with NO
 * server 500, so feature tests never see it (this is exactly how the Arsenal
 * Sheet shipped blank after card_creator.edit → tools.card_creator.edit).
 *
 * This guards that class: every string-literal route('name', …) call in the
 * frontend must reference a registered (Ziggy-exposed) route.
 */
it('every literal route() name used in the frontend is a registered route', function () {
    $registered = collect(Route::getRoutes()->getRoutesByName())->keys();

    $missing = [];
    foreach (File::allFiles(resource_path('js')) as $file) {
        if (! in_array($file->getExtension(), ['vue', 'ts'], true)) {
            continue;
        }
        $contents = (string) file_get_contents($file->getRealPath());
        // Match route('name' or route("name" — literal names only (dynamic
        // names built from variables/template literals are skipped).
        preg_match_all('/\broute\(\s*[\'"]([a-zA-Z0-9_.\-]+)[\'"]/', $contents, $m);
        foreach ($m[1] as $name) {
            if (! $registered->contains($name)) {
                $missing[] = "{$name}  ({$file->getRelativePathname()})";
            }
        }
    }

    expect($missing)->toBe([], "Unregistered route() names referenced in the frontend:\n".implode("\n", array_unique($missing)));
});
