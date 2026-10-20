<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PoolSeasonEnum;
use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StrategyRequest;
use App\Models\Strategy;
use App\Models\Token;
use Illuminate\Http\Request;
use Storage;
use Str;

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
            'all_tokens' => Token::orderBy('name')->toSelectOptions('name', 'id'),
        ]);
    }

    public function edit(Request $request, Strategy $strategy)
    {
        return inertia('Admin/Strategies/StrategyForm', [
            'strategy' => $strategy->load('tokens:id'),
            'seasons' => PoolSeasonEnum::toSelectOptions(),
            'suits' => SuitEnum::toSelectOptions(),
            'all_tokens' => Token::orderBy('name')->toSelectOptions('name', 'id'),
        ]);
    }

    public function store(StrategyRequest $request)
    {
        $strategy = $this->validateAndSave($request);

        return redirect()->route('admin.strategies.index')->withMessage("{$strategy->name} created successfully.");
    }

    public function update(StrategyRequest $request, Strategy $strategy)
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

    private function validateAndSave(StrategyRequest $request, ?Strategy $strategy = null): Strategy
    {
        $validated = $request->validated();

        // setup/rules/scoring are NOT NULL text columns; a blank form field arrives as null.
        foreach (['setup', 'rules', 'scoring'] as $column) {
            $validated[$column] ??= '';
        }

        $tokenIds = $validated['token_ids'] ?? [];
        unset($validated['token_ids']);

        // Handle Images
        $nameSlug = Str::slug($validated['name']);
        if (! empty($validated['image'])) {
            $extension = $validated['image']->extension();
            $uuid = Str::uuid();
            $fileName = sprintf('%s_%s.%s', Str::slug($nameSlug), $uuid, $extension);
            $filePath = "strategies/{$nameSlug}/{$fileName}";
            Storage::disk('public')->put($filePath, file_get_contents($validated['image']));
            $validated['image'] = $filePath;
        } else {
            unset($validated['image']);
        }

        if (! ($strategy)) {
            $strategy = Strategy::create($validated);
        } else {
            $strategy->update($validated);
        }

        $strategy->tokens()->sync($tokenIds);

        return $strategy;
    }
}
