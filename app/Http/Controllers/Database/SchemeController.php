<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Controller;
use App\Models\Scheme;
use Inertia\Response;

class SchemeController extends Controller
{
    public function view(Scheme $scheme): Response
    {
        $scheme->load('nextSchemeOne', 'nextSchemeTwo', 'nextSchemeThree');

        return inertia('Seasons/SchemeView', [
            'scheme' => [
                'id' => $scheme->id,
                'name' => $scheme->name,
                'slug' => $scheme->slug,
                'season' => $scheme->season->value,
                'season_label' => $scheme->season->label(),
                'selector' => $scheme->selector,
                'prerequisite' => $scheme->prerequisite,
                'reveal' => $scheme->reveal,
                'scoring' => $scheme->scoring,
                'additional' => $scheme->additional,
                'image_url' => $scheme->image_url,
                'next_schemes' => collect([
                    $scheme->nextSchemeOne,
                    $scheme->nextSchemeTwo,
                    $scheme->nextSchemeThree,
                ])->filter()->map(fn (Scheme $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                ])->values(),
            ],
        ]);
    }
}
