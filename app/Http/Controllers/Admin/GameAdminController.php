<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameFormatEnum;
use App\Enums\GameStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Read-only Game Tracker monitoring list — sits alongside the other
 * Diagnostics tools (Activity Log, Sessions, Failed Jobs), not the
 * catalog-content admin pages. Super-admin only (see routes/admin.php).
 */
class GameAdminController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = ['name', 'status', 'format', 'current_turn', 'created_at', 'updated_at'];

    public function index(Request $request): Response|ResponseFactory
    {
        $sort = $request->query('sort');
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'updated_at';

        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $status = $request->query('status');
        $format = $request->query('format');
        $isSolo = $request->query('is_solo');
        $search = $request->query('search');

        $query = Game::query()->with(['creator:id,name', 'players.user:id,name', 'winner:id,name']);

        if ($status && GameStatusEnum::tryFrom($status)) {
            $query->where('status', $status);
        }

        if ($format && GameFormatEnum::tryFrom($format)) {
            $query->where('format', $format);
        }

        if ($isSolo === '1' || $isSolo === '0') {
            $query->where('is_solo', $isSolo === '1');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('creator', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('players.user', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        $games = $query->orderBy($sort, $direction)
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Game $game) => [
                'id' => $game->id,
                'uuid' => $game->uuid,
                'name' => $game->name,
                'status' => $game->status->value,
                'status_label' => $game->status->label(),
                'format' => $game->format->value,
                'format_label' => $game->format->label(),
                'current_turn' => $game->current_turn,
                'max_turns' => $game->max_turns,
                'creator' => $game->creator ? ['id' => $game->creator->id, 'name' => $game->creator->name] : null,
                'players' => $game->players->map(fn ($p) => $p->user ? ['id' => $p->user->id, 'name' => $p->user->name] : null)
                    ->filter()
                    ->values(),
                'winner' => $game->winner ? ['id' => $game->winner->id, 'name' => $game->winner->name] : null,
                'is_solo' => $game->is_solo,
                'is_tie' => $game->is_tie,
                'is_observable' => $game->is_observable,
                'created_at' => $game->created_at->diffForHumans(),
                'created_at_iso' => $game->created_at->toIso8601String(),
                'updated_at' => $game->updated_at->diffForHumans(),
                'updated_at_iso' => $game->updated_at->toIso8601String(),
            ]);

        return inertia('Admin/Games/Index', [
            'games' => $games,
            'filters' => [
                'sort' => $sort,
                'direction' => $direction,
                'status' => $status,
                'format' => $format,
                'is_solo' => $isSolo,
                'search' => $search,
            ],
            'statuses' => GameStatusEnum::toSelectOptions(),
            'formats' => GameFormatEnum::toSelectOptions(),
        ]);
    }
}
