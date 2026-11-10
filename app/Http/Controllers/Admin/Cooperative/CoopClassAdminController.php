<?php

namespace App\Http\Controllers\Admin\Cooperative;

use App\Enums\CoopRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cooperative\CoopClassRequest;
use App\Models\Cooperative\CoopClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

class CoopClassAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        return inertia('Admin/Cooperative/Classes/Index', [
            'items' => CoopClass::query()->orderBy('role')->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'role', 'is_unique', 'resistance', 'speed', 'max_health', 'elite_max_health', 'is_published'])
                ->map(fn (CoopClass $c) => [...$c->only(['id', 'name', 'is_unique', 'resistance', 'speed', 'max_health', 'elite_max_health', 'is_published']), 'role' => $c->role->value]),
            'roles' => CoopRoleEnum::toSelectOptions(),
        ]);
    }

    public function create(Request $request): Response|ResponseFactory
    {
        return inertia('Admin/Cooperative/Classes/Form', ['item' => null, 'roles' => CoopRoleEnum::toSelectOptions()]);
    }

    public function edit(Request $request, CoopClass $coopClass): Response|ResponseFactory
    {
        return inertia('Admin/Cooperative/Classes/Form', ['item' => $coopClass, 'roles' => CoopRoleEnum::toSelectOptions()]);
    }

    public function store(CoopClassRequest $request): RedirectResponse
    {
        $row = CoopClass::create($request->payload());

        return redirect()->route('admin.cooperative.classes.index')->withMessage("{$row->name} created.");
    }

    public function update(CoopClassRequest $request, CoopClass $coopClass): RedirectResponse
    {
        $coopClass->update($request->payload());

        return redirect()->route('admin.cooperative.classes.index')->withMessage("{$coopClass->name} updated.");
    }

    public function delete(Request $request, CoopClass $coopClass): RedirectResponse
    {
        $name = $coopClass->name;
        $coopClass->delete();

        return redirect()->route('admin.cooperative.classes.index')->withMessage("{$name} deleted.");
    }
}
