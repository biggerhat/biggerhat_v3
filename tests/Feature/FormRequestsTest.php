<?php

use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\Finder\Finder;

/*
 * Guards every FormRequest against broken rule definitions (missing imports,
 * bad enum/model references) — mechanical conversions are the usual culprit.
 */
function formRequestClasses(): array
{
    $classes = [];
    foreach ((new Finder)->files()->in(app_path('Http/Requests'))->name('*.php') as $file) {
        $class = 'App\\Http\\Requests\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
        if (is_subclass_of($class, FormRequest::class)) {
            $classes[] = $class;
        }
    }

    return $classes;
}

it('builds rules for every form request', function () {
    // Rules that read the bound route model / signed-in user can't be built without a real request.
    $needsContext = [
        App\Http\Requests\Games\UpdateCrewMemberRequest::class,
        App\Http\Requests\Admin\RenameUserRequest::class,
        App\Http\Requests\Settings\ProfileUpdateRequest::class,
    ];
    // Deliberately rule-less (authorization only).
    $noRules = [App\Http\Requests\Campaign\RemoveEquipmentRequest::class];

    $broken = [];
    foreach (array_diff(formRequestClasses(), $needsContext) as $class) {
        try {
            $request = $class::create('/', 'POST');
            $request->setContainer(app());
            $rules = $request->rules();
            if (! is_array($rules) || ($rules === [] && ! in_array($class, $noRules, true))) {
                $broken[] = "{$class}: no rules";
            }
            // Rule objects must be constructible and validators buildable.
            validator([], $rules)->passes();
        } catch (Throwable $e) {
            $broken[] = "{$class}: ".$e->getMessage();
        }
    }

    expect($broken)->toBe([]);
});
