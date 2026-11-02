<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignArsenalModelInjury;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;

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

        $aftermaths = CampaignAftermath::query()
            ->whereIn('campaign_game_id', $games->pluck('id'))
            ->get(['id', 'campaign_game_id', 'campaign_crew_id', 'story_entry'])
            ->groupBy('campaign_game_id');

        $injuriesByAftermath = CampaignArsenalModelInjury::query()
            ->whereIn('acquired_aftermath_id', $aftermaths->flatten(1)->pluck('id'))
            ->with(['arsenalModel.character:id,display_name', 'customCharacter:id,name', 'injury:id,name'])
            ->get()
            ->groupBy('acquired_aftermath_id');

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
                'stories' => $this->storiesFor($aftermaths->get($g->id, collect()), $crewName),
                'injuries' => $this->injuriesFor($aftermaths->get($g->id, collect()), $injuriesByAftermath, $crewName),
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

    /**
     * @param  SupportCollection<int, CampaignAftermath>  $aftermaths
     * @return list<array{crew: string|null, text: string}>
     */
    private function storiesFor(SupportCollection $aftermaths, callable $crewName): array
    {
        return $aftermaths
            ->filter(fn (CampaignAftermath $a) => trim((string) $a->story_entry) !== '')
            ->map(fn (CampaignAftermath $a) => ['crew' => $crewName($a->campaign_crew_id), 'text' => trim((string) $a->story_entry)])
            ->values()
            ->all();
    }

    /**
     * @param  SupportCollection<int, CampaignAftermath>  $aftermaths
     * @param  SupportCollection<int, Collection<int, CampaignArsenalModelInjury>>  $injuriesByAftermath
     * @return list<array{crew: string|null, model: string, injury: string}>
     */
    private function injuriesFor(SupportCollection $aftermaths, SupportCollection $injuriesByAftermath, callable $crewName): array
    {
        $rows = [];
        foreach ($aftermaths as $aftermath) {
            foreach ($injuriesByAftermath->get($aftermath->id, []) as $injury) {
                $rows[] = [
                    'crew' => $crewName($aftermath->campaign_crew_id),
                    'model' => $injury->arsenalModel->character->display_name ?? $injury->customCharacter->name ?? 'Model',
                    'injury' => $injury->injury->name ?? 'Injury',
                ];
            }
        }

        return $rows;
    }
}
