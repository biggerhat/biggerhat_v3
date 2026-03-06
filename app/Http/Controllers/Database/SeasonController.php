<?php

namespace App\Http\Controllers\Database;

use App\Enums\DeploymentEnum;
use App\Enums\PoolSeasonEnum;
use App\Http\Controllers\Controller;
use App\Models\Scheme;
use App\Models\Strategy;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class SeasonController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('seasons.view', PoolSeasonEnum::defaultSeason()->value);
    }

    public function view(string $season): Response
    {
        $seasonEnum = PoolSeasonEnum::tryFrom($season);

        if (! $seasonEnum) {
            abort(404);
        }

        $strategies = Strategy::forSeason($seasonEnum)->orderBy('name')->get()->map(fn (Strategy $strategy) => [
            'id' => $strategy->id,
            'name' => $strategy->name,
            'slug' => $strategy->slug,
            'suit' => $strategy->suit?->value,
            'suit_label' => $strategy->suit?->label(),
            'setup' => $strategy->setup,
            'rules' => $strategy->rules,
            'scoring' => $strategy->scoring,
            'additional_scoring' => $strategy->additional_scoring,
            'image_url' => $strategy->image_url,
        ]);

        $schemes = Scheme::forSeason($seasonEnum)->orderBy('name')->get()->map(fn (Scheme $scheme) => [
            'id' => $scheme->id,
            'name' => $scheme->name,
            'slug' => $scheme->slug,
            'selector' => $scheme->selector,
            'prerequisite' => $scheme->prerequisite,
            'reveal' => $scheme->reveal,
            'scoring' => $scheme->scoring,
            'additional' => $scheme->additional,
            'image_url' => $scheme->image_url,
        ]);

        return inertia('Seasons/Index', [
            'season' => [
                'value' => $seasonEnum->value,
                'label' => $seasonEnum->label(),
            ],
            'seasons' => collect(PoolSeasonEnum::cases())->map(fn (PoolSeasonEnum $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'deployments' => collect(DeploymentEnum::cases())->map(fn (DeploymentEnum $d) => [
                'value' => $d->value,
                'label' => $d->label(),
                'suit' => $d->suit()->value,
                'suit_label' => $d->suit()->label(),
                'description' => $d->description(),
                'image_url' => $d->imageUrl(),
            ]),
            'strategies' => $strategies,
            'schemes' => $schemes,
        ]);
    }
}
