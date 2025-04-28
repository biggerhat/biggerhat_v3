<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PoolSeasonEnum;
use App\Http\Controllers\Controller;
use App\Models\Scheme;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function store(Request $request)
    {
        $scheme = $this->validateAndSave($request);

        return redirect()->route('admin.schemes.index')->withMessage("{$scheme->name} created successfully.");
    }

    public function update(Request $request, Scheme $scheme)
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

    private function validateAndSave(Request $request, ?Scheme $scheme = null): Scheme
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'season' => ['required', 'string', Rule::enum(PoolSeasonEnum::class)],
            'selector' => ['nullable', 'string'],
            'prerequisite' => ['nullable', 'string'],
            'reveal' => ['nullable', 'string'],
            'scoring' => ['nullable', 'string'],
            'additional' => ['nullable', 'string'],
            'image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'next_scheme_one_id' => ['nullable', 'integer'],
            'next_scheme_two_id' => ['nullable', 'integer'],
            'next_scheme_three_id' => ['nullable', 'integer'],
        ]);

        // Handle Images
        $nameSlug = Str::slug($validated['name']);
        if ($validated['image']) {
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
