<?php

namespace App\Http\Controllers;

use App\Enums\GameStatusEnum;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

class StatsController extends Controller
{
    public function myStats(): \Illuminate\Http\RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        return redirect()->route('stats.show', $user->slug);
    }

    public function show(User $user): Response
    {
        // Get all completed game player records for this user
        $playerRecords = GamePlayer::where('user_id', $user->id)
            ->whereHas('game', fn ($q) => $q->where('status', GameStatusEnum::Completed))
            ->with(['game:id,uuid,status,encounter_size,is_solo,is_tie,winner_id,winner_slot,completed_at,strategy_id', 'game.strategy:id,name'])
            ->get();

        if ($playerRecords->isEmpty()) {
            return inertia('Profile/Stats', [
                'user' => ['name' => $user->name, 'slug' => $user->slug],
                'stats' => $this->emptyStats(),
                'is_own_profile' => Auth::id() === $user->id,
            ]);
        }

        $gameIds = $playerRecords->pluck('game_id')->unique();

        // Win/loss/tie
        $wins = 0;
        $losses = 0;
        $ties = 0;
        $soloGames = 0;
        $duelGames = 0;
        $totalVp = 0;

        foreach ($playerRecords as $pr) {
            $game = $pr->game;
            $totalVp += $pr->total_points;

            if ($game->is_solo) {
                $soloGames++;
            } else {
                $duelGames++;
            }

            if ($game->is_tie) {
                $ties++;
            } elseif ($game->winner_id === $user->id || ($game->is_solo && $game->winner_slot === $pr->slot)) {
                $wins++;
            } else {
                $losses++;
            }
        }

        $totalGames = $playerRecords->count();
        $avgVp = $totalGames > 0 ? round($totalVp / $totalGames, 1) : 0;
        $winRate = $totalGames > 0 ? round(($wins / $totalGames) * 100) : 0;

        // Faction breakdown
        $factionStats = $playerRecords->groupBy(fn ($pr) => $pr->getRawOriginal('faction'))->map(function ($records, $faction) use ($user) {
            $w = $records->filter(fn ($pr) => ! $pr->game->is_tie && ($pr->game->winner_id === $user->id || ($pr->game->is_solo && $pr->game->winner_slot === $pr->slot)))->count();
            $t = $records->filter(fn ($pr) => $pr->game->is_tie)->count();
            $l = $records->count() - $w - $t;

            return [
                'faction' => $faction,
                'games' => $records->count(),
                'wins' => $w,
                'losses' => $l,
                'ties' => $t,
                'win_rate' => $records->count() > 0 ? round(($w / $records->count()) * 100) : 0,
                'avg_vp' => $records->count() > 0 ? round($records->avg('total_points'), 1) : 0,
            ];
        })->sortByDesc('games')->values()->toArray();

        // Master breakdown
        $masterStats = $playerRecords->filter(fn (GamePlayer $pr) => (bool) $pr->master_name)->groupBy('master_name')->map(function ($records, $masterName) use ($user) {
            $first = $records->first();
            $w = $records->filter(fn ($pr) => ! $pr->game->is_tie && ($pr->game->winner_id === $user->id || ($pr->game->is_solo && $pr->game->winner_slot === $pr->slot)))->count();
            $t = $records->filter(fn ($pr) => $pr->game->is_tie)->count();
            $l = $records->count() - $w - $t;

            return [
                'master_name' => $masterName,
                'faction' => $first->getRawOriginal('faction'),
                'games' => $records->count(),
                'wins' => $w,
                'losses' => $l,
                'ties' => $t,
                'win_rate' => $records->count() > 0 ? round(($w / $records->count()) * 100) : 0,
                'avg_vp' => $records->count() > 0 ? round($records->avg('total_points'), 1) : 0,
            ];
        })->sortByDesc('games')->values()->toArray();

        // Scheme stats
        $turns = GameTurn::whereIn('game_id', $gameIds)
            ->whereIn('game_player_id', $playerRecords->pluck('id'))
            ->whereNotNull('scheme_id')
            ->with('scheme:id,name')
            ->get();

        $schemeStats = $turns->filter(fn ($t) => $t->scheme_points > 0)->groupBy('scheme_id')->map(function ($schemeTurns) {
            $scheme = $schemeTurns->first()->scheme;

            return [
                'name' => $scheme->name ?? 'Unknown',
                'times_scored' => $schemeTurns->count(),
                'total_vp' => $schemeTurns->sum('scheme_points'),
            ];
        })->sortByDesc('total_vp')->values()->take(10)->toArray();

        $totalSchemeVp = $turns->sum('scheme_points');
        $totalStrategyVp = $turns->sum('strategy_points');
        $avgSchemeVpPerGame = $totalGames > 0 ? round($totalSchemeVp / $totalGames, 1) : 0;
        $avgStrategyVpPerGame = $totalGames > 0 ? round($totalStrategyVp / $totalGames, 1) : 0;

        // Keyword matchup matrix (duel games only)
        $matchups = $this->buildKeywordMatchups($user, $playerRecords);

        // Recent games
        $recentGames = $playerRecords->sortByDesc(fn ($pr) => $pr->game->completed_at)->take(10)->map(function ($pr) use ($user) {
            $game = $pr->game;
            $result = $game->is_tie ? 'tie' : (($game->winner_id === $user->id || ($game->is_solo && $game->winner_slot === $pr->slot)) ? 'win' : 'loss');

            return [
                'uuid' => $game->uuid,
                'result' => $result,
                'faction' => $pr->getRawOriginal('faction'),
                'master_name' => $pr->master_name,
                'total_points' => $pr->total_points,
                'encounter_size' => $game->encounter_size,
                'is_solo' => $game->is_solo,
                'strategy' => $game->strategy?->name,
                'completed_at' => $game->completed_at?->toDateString(),
            ];
        })->values()->toArray();

        return inertia('Profile/Stats', [
            'user' => ['name' => $user->name, 'slug' => $user->slug],
            'stats' => [
                'total_games' => $totalGames,
                'wins' => $wins,
                'losses' => $losses,
                'ties' => $ties,
                'win_rate' => $winRate,
                'solo_games' => $soloGames,
                'duel_games' => $duelGames,
                'total_vp' => $totalVp,
                'avg_vp' => $avgVp,
                'avg_scheme_vp' => $avgSchemeVpPerGame,
                'avg_strategy_vp' => $avgStrategyVpPerGame,
                'faction_stats' => $factionStats,
                'master_stats' => $masterStats,
                'scheme_stats' => $schemeStats,
                'matchups' => $matchups,
                'recent_games' => $recentGames,
            ],
            'is_own_profile' => Auth::id() === $user->id,
        ]);
    }

    private function buildKeywordMatchups(User $user, $playerRecords): array
    {
        // Only duel (non-solo) completed games
        $duelRecords = $playerRecords->filter(fn ($pr) => ! $pr->game->is_solo);
        if ($duelRecords->isEmpty()) {
            return [];
        }

        $gameIds = $duelRecords->pluck('game_id')->unique();

        // Load all players for these games with master keywords
        $allPlayers = GamePlayer::whereIn('game_id', $gameIds)
            ->whereNotNull('master_id')
            ->with('master.keywords:id,name,slug')
            ->get()
            ->groupBy('game_id');

        $matchups = [];

        foreach ($duelRecords as $myRecord) {
            $gamePlayers = $allPlayers->get($myRecord->game_id);
            if (! $gamePlayers || $gamePlayers->count() < 2) {
                continue;
            }

            $opponent = $gamePlayers->first(fn ($p) => $p->id !== $myRecord->id);
            if (! $opponent?->master?->keywords || ! $myRecord->master_id) {
                continue;
            }

            // Load my master's keywords if not already loaded
            $myRecord->loadMissing('master.keywords:id,name,slug');
            $myKeywords = $myRecord->master->keywords->pluck('name')->toArray();
            $oppKeywords = $opponent->master->keywords->pluck('name')->toArray();

            $game = $myRecord->game;
            $result = $game->is_tie ? 'tie' : (($game->winner_id === $user->id) ? 'win' : 'loss');

            // Cross-product: each of my keywords vs each of opponent's keywords
            foreach ($myKeywords as $myKw) {
                foreach ($oppKeywords as $oppKw) {
                    $key = $myKw.'|'.$oppKw;
                    if (! isset($matchups[$key])) {
                        $matchups[$key] = ['my_keyword' => $myKw, 'opp_keyword' => $oppKw, 'wins' => 0, 'losses' => 0, 'ties' => 0, 'games' => 0];
                    }
                    $matchups[$key]['games']++;
                    $matchups[$key][$result === 'win' ? 'wins' : ($result === 'loss' ? 'losses' : 'ties')]++;
                }
            }
        }

        // Calculate win rates and sort
        return collect($matchups)->map(function ($m) {
            $m['win_rate'] = $m['games'] > 0 ? round(($m['wins'] / $m['games']) * 100) : 0;

            return $m;
        })->sortByDesc('games')->values()->toArray();
    }

    private function emptyStats(): array
    {
        return [
            'total_games' => 0,
            'wins' => 0,
            'losses' => 0,
            'ties' => 0,
            'win_rate' => 0,
            'solo_games' => 0,
            'duel_games' => 0,
            'total_vp' => 0,
            'avg_vp' => 0,
            'avg_scheme_vp' => 0,
            'avg_strategy_vp' => 0,
            'faction_stats' => [],
            'master_stats' => [],
            'scheme_stats' => [],
            'matchups' => [],
            'recent_games' => [],
        ];
    }
}
