<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PoolSeasonEnum;
use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Models\Strategy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StrategyAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Strategies/Index', [
            'strategies' => Strategy::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Strategies/StrategyForm', [
            'seasons' => PoolSeasonEnum::toSelectOptions(),
            'suits' => SuitEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Strategy $strategy)
    {
        return inertia('Admin/Strategies/StrategyForm', [
            'strategy' => $strategy,
            'seasons' => PoolSeasonEnum::toSelectOptions(),
            'suits' => SuitEnum::toSelectOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $strategy = $this->validateAndSave($request);

        return redirect()->route('admin.strategies.index')->withMessage("{$strategy->name} created successfully.");
    }

    public function update(Request $request, Strategy $strategy)
    {
        $strategy = $this->validateAndSave($request, $strategy);

        return redirect()->route('admin.strategies.index')->withMessage("{$strategy->name} has been updated.");
    }

    public function delete(Request $request, Strategy $strategy)
    {
        $name = $strategy->name;
        $strategy->delete();

        return redirect()->route('admin.strategies.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(Request $request, ?Strategy $strategy = null): Strategy
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'season' => ['required', 'string', Rule::enum(PoolSeasonEnum::class)],
            'suit' => ['nullable', 'string', Rule::enum(SuitEnum::class)],
            'setup' => ['nullable', 'string'],
            'rules' => ['nullable', 'string'],
            'scoring' => ['nullable', 'string'],
        ]);

        if (! ($strategy)) {
            $strategy = Strategy::create($validated);
        } else {
            $strategy->update($validated);
        }

        return $strategy;
    }
}
