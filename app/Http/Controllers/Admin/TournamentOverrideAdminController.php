<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Support\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Super-admin escape hatch for the tournament state machine. Bypasses the
 * normal canTransitionRoundTo / canEditScenario gates to fix tournaments
 * stuck in bad states (TO clicked Start prematurely, round confirmed by
 * mistake, etc.). Every action here is logged via AdminAudit.
 */
class TournamentOverrideAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        $tournaments = Tournament::query()
            ->withCount(['players', 'rounds'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Tournament $t) => [
                'id' => $t->id,
                'uuid' => $t->uuid,
                'name' => $t->name,
                'status' => $t->status->value,
                'players_count' => $t->players_count,
                'rounds_count' => $t->rounds_count,
                'event_date' => $t->event_date->toDateString(),
            ]);

        return inertia('Admin/Tournaments/Index', [
            'tournaments' => $tournaments,
            'tournament_statuses' => collect(TournamentStatusEnum::cases())
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])
                ->values(),
        ]);
    }

    public function show(Request $request, Tournament $tournament): Response|ResponseFactory
    {
        $tournament->load(['rounds', 'players']);

        return inertia('Admin/Tournaments/Show', [
            'tournament' => [
                'id' => $tournament->id,
                'uuid' => $tournament->uuid,
                'name' => $tournament->name,
                'status' => $tournament->status->value,
                'event_date' => $tournament->event_date->toDateString(),
                'rounds' => $tournament->rounds->map(fn (TournamentRound $r) => [
                    'id' => $r->id,
                    'round_number' => $r->round_number,
                    'status' => $r->status->value,
                    'started_at' => $r->started_at?->toIso8601String(),
                    'completed_at' => $r->completed_at?->toIso8601String(),
                ]),
                'players' => $tournament->players->map(fn (TournamentPlayer $p) => [
                    'id' => $p->id,
                    'display_name' => $p->display_name,
                    'is_disqualified' => (bool) $p->is_disqualified,
                    'dropped_after_round' => $p->dropped_after_round,
                ]),
            ],
            'tournament_statuses' => collect(TournamentStatusEnum::cases())
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])
                ->values(),
            'round_statuses' => collect(TournamentRoundStatusEnum::cases())
                ->map(fn ($c) => ['value' => $c->value, 'label' => ucfirst(str_replace('_', ' ', $c->value))])
                ->values(),
        ]);
    }

    public function forceTournamentStatus(Request $request, Tournament $tournament): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $next = TournamentStatusEnum::tryFrom($data['status']);
        if (! $next) {
            return back()->withMessage('Unknown status.');
        }

        $previous = $tournament->status;
        $tournament->update(['status' => $next]);
        AdminAudit::log('Forced tournament status', $tournament, [
            'from' => $previous->value,
            'to' => $next->value,
            'reason' => $data['reason'] ?? null,
        ]);

        return back()->withMessage("Tournament forced to {$next->label()}.");
    }

    public function forceRoundStatus(Request $request, Tournament $tournament, TournamentRound $round): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $next = TournamentRoundStatusEnum::tryFrom($data['status']);
        if (! $next || $round->tournament_id !== $tournament->id) {
            return back()->withMessage('Invalid status or round mismatch.');
        }

        // Side-effects mirror what the regular state machine does so the round
        // doesn't end up half-transitioned (no started_at on InProgress, etc.).
        $update = ['status' => $next];
        if ($next === TournamentRoundStatusEnum::InProgress && ! $round->started_at) {
            $update['started_at'] = now();
        }
        if ($next === TournamentRoundStatusEnum::Completed && ! $round->completed_at) {
            $update['completed_at'] = now();
        }
        $previous = $round->status;
        $round->update($update);
        AdminAudit::log('Forced round status', $round, [
            'tournament_id' => $tournament->id,
            'from' => $previous->value,
            'to' => $next->value,
            'reason' => $data['reason'] ?? null,
        ]);

        return back()->withMessage("Round {$round->round_number} forced to {$next->value}.");
    }

    public function destroyTournament(Request $request, Tournament $tournament): RedirectResponse
    {
        // Soft-delete via the SoftDeletes trait. Hard-delete intentionally not
        // exposed — bring it back manually if recovery is needed.
        AdminAudit::log('Soft-deleted tournament '.$tournament->name, $tournament);
        $tournament->delete();

        return redirect()->route('admin.tournaments.index')->withMessage('Tournament deleted (soft).');
    }
}
