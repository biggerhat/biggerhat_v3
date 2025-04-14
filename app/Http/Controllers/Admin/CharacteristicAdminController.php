<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Characteristic;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CharacteristicAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Characteristics/Index', [
            'characteristics' => Characteristic::all(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Characteristics/CharacteristicForm');
    }

    public function edit(Request $request, Characteristic $characteristic)
    {
        return inertia('Admin/Characteristics/CharacteristicForm', [
            'characteristic' => $characteristic,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $characteristic = Characteristic::create($validated);

        return redirect()->route('admin.characteristics.index')->withMessage("{$characteristic->name} created successfully.");
    }

    public function update(Request $request, Characteristic $characteristic)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $characteristic->update($validated);

        return redirect()->route('admin.characteristics.index')->withMessage("{$characteristic->name} has been updated.");
    }

    public function delete(Request $request, Characteristic $characteristic)
    {
        $name = $characteristic->name;
        $characteristic->delete();

        return redirect()->route('admin.characteristics.index')->withMessage("{$name} has been deleted.");
    }
}
