<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BaseSizeEnum;
use App\Http\Controllers\Controller;
use App\Models\Marker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarkerAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Markers/Index', [
            'markers' => Marker::all(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Markers/MarkerForm', [
            'base_sizes' => BaseSizeEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Marker $marker)
    {
        return inertia('Admin/Markers/MarkerForm', [
            'marker' => $marker,
            'base_sizes' => BaseSizeEnum::toSelectOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base' => ['required', 'integer', Rule::enum(BaseSizeEnum::class)],
            'description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $marker = Marker::create($validated);

        return redirect()->route('admin.markers.index')->withMessage("{$marker->name} created successfully.");
    }

    public function update(Request $request, Marker $marker)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base' => ['required', 'integer', Rule::enum(BaseSizeEnum::class)],
            'description' => ['nullable', 'string'],
        ]);

        $marker->update($validated);

        return redirect()->route('admin.markers.index')->withMessage("{$marker->name} has been updated.");
    }

    public function delete(Request $request, Marker $marker)
    {
        $name = $marker->name;
        $marker->delete();

        return redirect()->route('admin.markers.index')->withMessage("{$name} has been deleted.");
    }
}
