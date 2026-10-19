<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PoolSeasonEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchemeRequest;
use App\Models\Scheme;
use Illuminate\Http\Request;
use Storage;
use Str;

class SchemeAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Schemes/Index', [
            'schemes' => Scheme::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Schemes/SchemeForm', [
            'seasons' => PoolSeasonEnum::toSelectOptions(),
            'schemes' => Scheme::toSelectOptions('name', 'id'),
        ]);
    }

    public function edit(Request $request, Scheme $scheme)
    {
        return inertia('Admin/Schemes/SchemeForm', [
            'scheme' => $scheme,
            'seasons' => PoolSeasonEnum::toSelectOptions(),
            'schemes' => Scheme::toSelectOptions('name', 'id'),
        ]);
    }

    public function store(SchemeRequest $request)
    {
        $scheme = $this->validateAndSave($request);

        return redirect()->route('admin.schemes.index')->withMessage("{$scheme->name} created successfully.");
    }

    public function update(SchemeRequest $request, Scheme $scheme)
    {
        $scheme = $this->validateAndSave($request, $scheme);

        return redirect()->route('admin.schemes.index')->withMessage("{$scheme->name} has been updated.");
    }

    public function delete(Request $request, Scheme $scheme)
    {
        $name = $scheme->name;
        $scheme->delete();

        return redirect()->route('admin.schemes.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(SchemeRequest $request, ?Scheme $scheme = null): Scheme
    {
        $validated = $request->validated();

        // reveal/scoring/additional are NOT NULL text columns; a blank form field arrives as null.
        foreach (['reveal', 'scoring', 'additional'] as $column) {
            $validated[$column] ??= '';
        }

        // Clean up requirements — normalize "none" to null
        if (! empty($validated['requirements'])) {
            $validated['requirements'] = array_map(function ($req) {
                if (($req['cost_operator'] ?? null) === 'none') {
                    $req['cost_operator'] = null;
                    $req['cost_value'] = null;
                }

                return $req;
            }, $validated['requirements']);
        }

        // Handle Images
        $nameSlug = Str::slug($validated['name']);
        if ($validated['image'] ?? null) {
            $extension = $validated['image']->extension();
            $uuid = Str::uuid();
            $fileName = sprintf('%s_%s.%s', Str::slug($nameSlug), $uuid, $extension);
            $filePath = "schemes/{$nameSlug}/{$fileName}";
            Storage::disk('public')->put($filePath, file_get_contents($validated['image']));
            $validated['image'] = $filePath;
        } else {
            unset($validated['image']);
        }

        if (! ($scheme)) {
            $scheme = Scheme::create($validated);
        } else {
            $scheme->update($validated);
        }

        return $scheme;
    }
}
