<?php

namespace App\Http\Controllers;

use App\Support\Tabletop\CharacterSearch;
use App\Support\Tabletop\TabletopAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Search for the character cards the tabletop sandbox can put on the table. Same pre-release gate as the tabletop itself. */
class TabletopCharactersController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(TabletopAccess::allows($request->user()), 404);

        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']]);

        return response()->json(['characters' => CharacterSearch::find($validated['q'])]);
    }
}
