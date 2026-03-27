<?php

namespace App\Http\Controllers\API\V1;

use App\Enums\FactionEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @tags Factions
 */
class FactionController extends Controller
{
    /**
     * List all factions
     *
     * Returns all factions with their value, label, color, and logo.
     *
     * @response array{data: array<int, array{value: string, label: string, color: string, logo: string}>}
     */
    public function index(): JsonResponse
    {
        $factions = collect(FactionEnum::cases())->map(fn (FactionEnum $faction) => [
            'value' => $faction->value,
            'label' => $faction->label(),
            'color' => $faction->color(),
            'logo' => $faction->logo(),
        ]);

        return response()->json(['data' => $factions]);
    }

    /**
     * Get a single faction
     *
     * Returns a faction with its value, label, color, logo, and character statistics.
     *
     * @response array{data: array{value: string, label: string, color: string, logo: string, stats: array{characters: int, miniatures: int, keywords: int}}}
     */
    public function show(string $faction): JsonResponse
    {
        $enum = FactionEnum::tryFrom($faction);

        if (! $enum) {
            abort(404, 'Faction not found.');
        }

        return response()->json([
            'data' => [
                'value' => $enum->value,
                'label' => $enum->label(),
                'color' => $enum->color(),
                'logo' => $enum->logo(),
                'stats' => $enum->getCharacterStats(),
            ],
        ]);
    }
}
