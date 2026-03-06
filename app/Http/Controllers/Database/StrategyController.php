<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Controller;
use App\Models\Strategy;
use Inertia\Response;

class StrategyController extends Controller
{
    public function view(Strategy $strategy): Response
    {
        return inertia('Seasons/StrategyView', [
            'strategy' => [
                'id' => $strategy->id,
                'name' => $strategy->name,
                'slug' => $strategy->slug,
                'season' => $strategy->season->value,
                'season_label' => $strategy->season->label(),
                'suit' => $strategy->suit?->value,
                'suit_label' => $strategy->suit?->label(),
                'setup' => $strategy->setup,
                'rules' => $strategy->rules,
                'scoring' => $strategy->scoring,
                'additional_scoring' => $strategy->additional_scoring,
                'image_url' => $strategy->image_url,
            ],
        ]);
    }
}
