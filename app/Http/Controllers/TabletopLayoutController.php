<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tabletop\StoreTabletopLayoutRequest;
use App\Http\Requests\Tabletop\UpdateTabletopLayoutRequest;
use App\Models\Game;
use App\Models\TabletopLayout;
use App\Support\Tabletop\Layouts;
use App\Support\Tabletop\TabletopAccess;
use App\Support\Tabletop\TabletopImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Saved terrain setups: keep the table from the sandbox or from a game, load it again, share it by link, and copy somebody else's.
 */
class TabletopLayoutController extends Controller
{
    public function store(StoreTabletopLayoutRequest $request): RedirectResponse
    {
        $this->ensureRoom($request);
        $data = $request->validated();

        TabletopLayout::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'terrain' => array_values($data['terrain']),
            'table_image' => $data['table_image'] ?? null,
            ...TabletopLayout::flagsFor($data['visibility'] ?? 'private'),
        ]);

        return redirect()->back();
    }

    public function update(UpdateTabletopLayoutRequest $request, TabletopLayout $layout): RedirectResponse
    {
        $data = $request->validated();
        if (isset($data['terrain'])) {
            $data['terrain'] = array_values($data['terrain']);
        }
        if (isset($data['visibility'])) {
            $data = [...$data, ...TabletopLayout::flagsFor($data['visibility'])];
            unset($data['visibility']);
        }
        $layout->update($data);

        return redirect()->back();
    }

    public function destroy(Request $request, TabletopLayout $layout): RedirectResponse
    {
        abort_unless(TabletopAccess::allows($request->user()), 404);
        abort_unless($layout->user_id === $request->user()->id, 403);
        $layout->delete();

        return redirect()->back();
    }

    /** Save a copy of a layout you can see (your own, or one shared with you) as one of yours. */
    public function copy(Request $request, TabletopLayout $layout): RedirectResponse
    {
        abort_unless(TabletopAccess::allows($request->user()), 404);
        abort_unless(Layouts::visibleTo($layout, $request->user()), 404);
        $this->ensureRoom($request);

        TabletopLayout::create([
            'user_id' => $request->user()->id,
            'name' => $layout->user_id === $request->user()->id ? $this->copyName($layout->name) : $layout->name,
            'description' => $layout->description,
            'terrain' => $layout->terrain,
            'table_image' => $layout->table_image,
            'copied_from' => $layout->uuid,
        ]);

        return redirect()->back();
    }

    /** Save the table of a game you can see (yours, or one its players made observable, such as a tournament game) as one of your layouts. */
    public function fromGame(Request $request, Game $game): RedirectResponse
    {
        abort_unless(TabletopAccess::allows($request->user()), 404);
        abort_unless(Gate::allows('view', $game) || $game->is_observable, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $terrain = $game->board_state['terrain'] ?? [];
        if (! is_array($terrain) || $terrain === []) {
            throw ValidationException::withMessages(['name' => 'There is no terrain on this table to save yet.']);
        }
        $this->ensureRoom($request);

        TabletopLayout::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'terrain' => array_values($terrain),
            'table_image' => in_array($game->board_state['table_image'] ?? null, TabletopImages::tableKeys(), true) ? $game->board_state['table_image'] : null,
            'copied_from' => $game->uuid,
        ]);

        return redirect()->back();
    }

    private function ensureRoom(Request $request): void
    {
        if (TabletopLayout::where('user_id', $request->user()->id)->count() >= TabletopLayout::PER_USER) {
            throw ValidationException::withMessages(['name' => 'You can keep up to '.TabletopLayout::PER_USER.' saved layouts. Delete one to make room.']);
        }
    }

    private function copyName(string $name): string
    {
        return mb_substr($name, 0, 72).' copy';
    }
}
