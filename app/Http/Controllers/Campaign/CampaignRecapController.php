<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Read-only campaign recap: standings plus a week-by-week list of the games played so far.
 */
class CampaignRecapController extends Controller
{
    use AuthorizesCampaignAccess;

    public function show(Request $request, Campaign $campaign)
    {
        $this->ensureCampaignMember($request, $campaign);

        /** @var Collection<int, CampaignCrew> $crews */
        $crews = $campaign->crews()->with('user:id,name')->get();
        $crewById = $crews->keyBy('id');

        $games = CampaignGame::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', '!=', 'setup')
            ->orderBy('week_number')
            ->orderBy('id')
            ->get();

        $crewName = fn (?int $id): ?string => $id ? ($crewById[$id]->name ?? null) : null;

        $weeks = $games->groupBy('week_number')->map(fn ($weekGames, $week) => [
            'week' => (int) $week,
            'games' => $weekGames->map(fn (CampaignGame $g) => [
                'id' => $g->id,
                'crew_a' => $crewName($g->crew_a_id),
                'crew_b' => $crewName($g->crew_b_id) ?? 'Solo',
                'vp_a' => $g->vp_a,
                'vp_b' => $g->vp_b,
                'winner' => $crewName($g->winner_crew_id),
                'withdrew' => $crewName($g->withdrew_crew_id),
            ])->values()->all(),
        ])->values()->all();

        $played = $games->flatMap(fn (CampaignGame $g) => array_filter([$g->crew_a_id, $g->crew_b_id]))->countBy();

        $standings = $crews
            ->map(fn (CampaignCrew $crew) => [
                'id' => $crew->id,
                'name' => $crew->name,
                'player' => $crew->user?->name,
                'wins' => (int) $crew->total_wins,
                'games' => (int) ($played[$crew->id] ?? 0),
                'scrip' => (int) $crew->scrip,
            ])
            ->sortBy([['wins', 'desc'], ['games', 'asc'], ['name', 'asc']])
            ->values()
            ->all();

        return inertia('Campaigns/Recap', [
            'campaign' => $campaign->only(['id', 'name', 'status', 'current_week', 'length_weeks']),
            'standings' => $standings,
            'weeks' => $weeks,
        ]);
    }
}
