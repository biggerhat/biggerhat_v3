<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\FactionEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\TournamentStandingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * A printable results sheet for a finished event: the podium and the full final standings. Public, like the
 * tournament page itself, but only once the event is completed (before that the standings are still moving).
 */
class TournamentResultsController extends Controller
{
    public function __construct(private readonly TournamentStandingsService $standings) {}

    public function pdf(Tournament $tournament)
    {
        abort_unless($tournament->status === TournamentStatusEnum::Completed, 404);

        $standings = collect($this->standings->compute($tournament))
            ->map(function (array $row): array {
                $row['faction_label'] = ($row['faction'] ?? null) ? (FactionEnum::tryFrom($row['faction'])?->label() ?? $row['faction']) : null;

                return $row;
            })
            ->values();

        $ranked = $standings->where('is_ringer', false)->values();
        $ruleset = $tournament->rulesetDefinition();

        $pdf = Pdf::loadView('PDF.TournamentResults', [
            'tournament' => $tournament,
            'rulesetLabel' => $ruleset->label,
            'standings' => $standings,
            'awards' => $tournament->awards()->whereNotNull('player_id')->with('player')->get(),
            'podium' => $ranked->filter(fn (array $row) => $row['rank'] !== null && $row['rank'] <= 3)->values(),
            'tiebreakers' => $tournament->tiebreakerKeys(),
            'isTeamEvent' => $tournament->isTeamEvent(),
            'organizer' => $tournament->creator->name,
            'url' => route('tournaments.view', $tournament),
        ]);

        return $pdf->stream(Str::slug($tournament->name).'-results.pdf');
    }
}
