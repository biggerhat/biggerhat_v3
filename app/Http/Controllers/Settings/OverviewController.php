<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\GameSystemEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignPlayer;
use App\Models\CrewBuild;
use App\Models\CustomCharacter;
use App\Models\Game;
use App\Models\SavedSearch;
use App\Models\TOS\Company;
use App\Models\TOS\Garrison;
use App\Models\Tournament;
use App\Models\TournamentRsvp;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\AchievementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __construct(private readonly AchievementService $achievements) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $campaigns = Campaign::query()
            ->whereIn('id', CampaignPlayer::query()->where('user_id', $user->id)->select('campaign_id'))
            ->where('status', CampaignStatusEnum::Active)
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'current_week', 'length_weeks'])
            ->map(fn (Campaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'current_week' => $c->current_week,
                'length_weeks' => $c->length_weeks,
            ]);

        $upcomingTournaments = Tournament::query()
            ->whereIn('id', TournamentRsvp::query()->where('user_id', $user->id)->select('tournament_id'))
            ->where('event_date', '>=', now()->toDateString())
            ->where('status', '!=', TournamentStatusEnum::Completed)
            ->orderBy('event_date')
            ->get(['id', 'uuid', 'name', 'event_date'])
            ->map(fn (Tournament $t) => [
                'uuid' => $t->uuid,
                'name' => $t->name,
                'event_date' => $t->event_date->toDateString(),
            ]);

        $wishlistIds = Wishlist::where('user_id', $user->id)->pluck('id');

        return Inertia::render('Settings/Overview', [
            'active_games' => Game::forUser($user->id)->active()->count(),
            'collection' => [
                'malifaux_miniatures' => (int) DB::table('user_miniatures')->where('user_id', $user->id)->sum('quantity'),
                'malifaux_packages' => DB::table('user_packages')
                    ->join('packages', 'packages.id', '=', 'user_packages.package_id')
                    ->where('user_packages.user_id', $user->id)
                    ->whereIn('packages.game_system', [GameSystemEnum::Malifaux->value, GameSystemEnum::Both->value])
                    ->count(),
                'tos_unit_sculpts' => (int) DB::table('user_unit_sculpts')->where('user_id', $user->id)->sum('quantity'),
            ],
            'wishlists' => [
                'count' => $wishlistIds->count(),
                'items' => WishlistItem::whereIn('wishlist_id', $wishlistIds)->count(),
            ],
            'crew_builds' => CrewBuild::where('user_id', $user->id)->where('is_archived', false)->count(),
            'campaigns' => $campaigns,
            'upcoming_tournaments' => $upcomingTournaments,
            'is_supporter' => (bool) $user->isSupporter(),
            'tos_companies' => Company::where('user_id', $user->id)->count(),
            'tos_garrisons' => Garrison::where('user_id', $user->id)->count(),
            'custom_cards' => CustomCharacter::where('user_id', $user->id)->count(),
            'saved_searches' => [
                'malifaux' => SavedSearch::where('user_id', $user->id)->where('game_system', 'malifaux')->count(),
                'tos' => SavedSearch::where('user_id', $user->id)->where('game_system', 'tos')->count(),
            ],
            'achievements' => Inertia::defer(fn () => $this->buildAchievements($user)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAchievements(User $user): array
    {
        $gameRecord = $this->achievements->gameRecordForUser($user->id);
        $tournamentRecord = $this->achievements->tournamentRecordForUser($user->id);

        $collection = $user->collectionMiniatures()->get();
        $collectionTotal = $collection->count();
        $collectionBuilt = $collection->where('pivot.is_built', true)->count();
        $collectionPainted = $collection->where('pivot.is_painted', true)->count();

        $publicCrews = CrewBuild::where('user_id', $user->id)
            ->where('is_public', true)
            ->where('is_archived', false)
            ->count();

        $badges = $this->achievements->computeBadges(
            $gameRecord['total_games'],
            $gameRecord['wins'],
            $collectionTotal,
            $collectionBuilt,
            $collectionPainted,
            $tournamentRecord['played'],
            $tournamentRecord['best_finish'],
            $publicCrews,
        );

        return [
            'badges' => $badges,
            'total_games' => $gameRecord['total_games'],
            'wins' => $gameRecord['wins'],
            'tournaments_played' => $tournamentRecord['played'],
            'best_tournament_finish' => $tournamentRecord['best_finish'],
        ];
    }
}
