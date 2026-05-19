<?php

namespace App\Http\Controllers;

use App\Enums\PoolSeasonEnum;
use App\Models\Scheme;
use Inertia\Response;

class SchemePathController extends Controller
{
    public function index(): Response
    {
        $seasonEnum = PoolSeasonEnum::tryFrom(request()->query('season', ''))
            ?? PoolSeasonEnum::defaultSeason();

        if (request()->has('season') && ! PoolSeasonEnum::tryFrom(request()->query('season', ''))) {
            abort(404);
        }

        $schemes = Scheme::forSeason($seasonEnum)
            ->orderBy('name')
            ->get()
            ->map(fn (Scheme $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'selector' => $s->selector,
                'prerequisite' => $s->prerequisite,
                'reveal' => $s->reveal,
                'scoring' => $s->scoring,
                'additional' => $s->additional,
                'image_url' => $s->image_url,
                'next_scheme_ids' => array_values(array_filter([
                    $s->next_scheme_one_id,
                    $s->next_scheme_two_id,
                    $s->next_scheme_three_id,
                ])),
            ]);

        return inertia('Tools/SchemePath', [
            'season' => [
                'value' => $seasonEnum->value,
                'label' => $seasonEnum->label(),
            ],
            'seasons' => fn () => collect(PoolSeasonEnum::cases())->map(fn (PoolSeasonEnum $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'schemes' => $schemes,
        ]);
    }
}
