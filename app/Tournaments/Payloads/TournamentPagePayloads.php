<?php

namespace App\Tournaments\Payloads;

use App\Enums\DeploymentEnum;
use App\Enums\EncounterTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Deployment;
use App\Models\TOS\Operation;
use App\Models\Tournament;
use App\Models\TournamentAward;
use App\Models\TournamentRound;
use App\Models\TournamentTable;
use App\Services\TournamentCutService;
use App\Services\TournamentStandingsService;
use App\Tournaments\CrewListCheck;
use Inertia\DeferProp;
use Inertia\Inertia;

/**
 * Builds the Inertia props for the tournament pages: the public view/display payload (with what only the people
 * involved may see redacted) and the organizer's Manage payload. Controllers authorize and render; the shaping lives here.
 */
class TournamentPagePayloads
{
    public function __construct(private readonly TournamentStandingsService $standings) {}

    /**
     * The public tournament payload shared by the view page and display mode.
     *
     * @return array<string, mixed>
     */
    public function forView(Tournament $tournament): array
    {
        $tournament->load([
            'players.user:id,name,meta_id',
            'players.user.meta:id,name',
            'players.meta:id,name',
            'players.allegiance:id,slug,name',
            'players.garrison:id,name,share_code,is_public',
            'players.members.user:id,name',
            'players.members.allegiance:id,slug,name',
            'players.members.garrison:id,name,share_code,is_public',
            'rsvps.user:id,name',
            'rsvps.allegiance:id,slug,name',
            'rounds.games.playerOne:id,display_name,faction,user_id',
            'rounds.games.playerTwo:id,display_name,faction,user_id',
            // Don't column-restrict trackerGame — Game has appended attributes
            // (season_label) that need other columns to compute.
            'rounds.games.trackerGame',
            'rounds.strategy',
            'organizers:id,name',
        ]);

        $standings = $this->standings->compute($tournament);
        $this->hidePrivateGarrisonCodes($tournament);
        $this->markWaitlist($tournament);
        $this->redactPublicPayload($tournament);

        // Fetch every scheme referenced by any round in a single query instead
        // of one query per round (previously N+1).
        $allSchemeIds = collect($tournament->rounds)
            ->flatMap(fn (TournamentRound $r) => is_array($r->scheme_pool) ? $r->scheme_pool : [])
            ->unique()
            ->values()
            ->all();
        $schemesById = empty($allSchemeIds)
            ? collect()
            : Scheme::whereIn('id', $allSchemeIds)->get()->keyBy('id');

        // TOS-style scenarios: resolve operation/deployment ids with two queries for the whole event.
        $tosScenario = $this->resolveTosScenarios($tournament->rounds);

        $roundsData = [];
        foreach ($tournament->rounds as $round) {
            /** @var TournamentRound $round */
            $deployment = null;
            /** @var \App\Enums\DeploymentEnum|null $deployEnum */
            $deployEnum = $round->deployment;
            if ($deployEnum) {
                $deployment = [
                    'value' => $deployEnum->value,
                    'label' => $deployEnum->label(),
                    'description' => $deployEnum->description(),
                    'image_url' => $deployEnum->imageUrl(),
                ];
            }

            /** @var Strategy|null $strategyModel */
            $strategyModel = $round->strategy;
            $strategy = null;
            if ($strategyModel) {
                $strategyModel->append('image_url');
                $strategy = [
                    'id' => $strategyModel->id,
                    'name' => $strategyModel->name,
                    'image_url' => $strategyModel->image_url,
                ];
            }

            $schemes = [];
            foreach ((array) $round->scheme_pool as $id) {
                /** @var Scheme|null $s */
                $s = $schemesById->get($id);
                if ($s) {
                    $schemes[] = [
                        'id' => $s->id,
                        'name' => $s->name,
                        'image_url' => $s->image_url,
                        'prerequisite' => $s->prerequisite,
                        'reveal' => $s->reveal,
                        'scoring' => $s->scoring,
                    ];
                }
            }

            /** @var \App\Enums\TournamentRoundStatusEnum $roundStatus */
            $roundStatus = $round->status;
            $roundsData[] = [
                'id' => $round->id,
                'round_number' => $round->round_number,
                'is_cut' => $round->is_cut,
                'label' => $round->label,
                'short_label' => $round->short_label,
                'status' => $roundStatus->value,
                'started_at' => $round->started_at?->toIso8601String(),
                'deployment' => $deployment,
                'strategy' => $strategy,
                'schemes' => $schemes,
                'tos_scenario' => $tosScenario[$round->id] ?? null,
                'games' => $round->games,
            ];
        }

        // Most-scored scheme across all tracker-linked games. Tournament-side
        // scoring records strategy/scheme as a sum, not per-pick — so the only
        // place the scheme breakdown lives is in GameTurn rows on the linked
        // tracker games. Coverage is partial (games where neither player has a
        // BiggerHat account aren't tracked), but it's enough to surface the
        // most-popular scheme of the event.
        $trackerGameIds = collect($roundsData)
            ->flatMap(fn ($r) => collect($r['games'])->pluck('game_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $schemeStats = null;
        if ($trackerGameIds && $schemesById->isNotEmpty()) {
            // Use the table query builder (not Eloquent) so PHPStan doesn't try
            // to map the SUM/COUNT projections back onto GameTurn properties.
            $top = \DB::table('game_turns')
                ->whereIn('game_id', $trackerGameIds)
                ->where('scheme_action', 'scored')
                ->where('scheme_points', '>', 0)
                ->whereNotNull('scheme_id')
                ->selectRaw('scheme_id, SUM(scheme_points) as total_points, COUNT(*) as scoring_turns')
                ->groupBy('scheme_id')
                ->orderByDesc('total_points')
                ->first();

            if ($top) {
                /** @var Scheme|null $scheme */
                $scheme = $schemesById->get($top->scheme_id);
                if ($scheme) {
                    $schemeStats = [
                        'most_scored' => [
                            'id' => $scheme->id,
                            'name' => $scheme->name,
                            'total_points' => (int) $top->total_points,
                            'scoring_turns' => (int) $top->scoring_turns,
                        ],
                    ];
                }
            }
        }

        // Unset loaded rounds to avoid double-serializing alongside roundsData.
        // Needs the rounds' games, so it is worked out before they are unset below.
        $crewLists = $this->crewListsFor($tournament);

        $tournament->unsetRelation('rounds');

        return [
            'tournament' => $tournament,
            'ruleset' => $this->ruleset($tournament),
            'tables' => $this->tableDetails($tournament),
            // Shows the scoring-desk link to organizers and scorekeepers.
            'can_score' => request()->user()?->can('score', $tournament) ?? false,
            'awards' => $this->awardList($tournament, false),
            'allegiances' => $this->allegianceOptions($tournament),
            'rounds' => $roundsData,
            'standings' => $standings,
            'factions' => FactionEnum::buildDetails(),
            'scheme_stats' => $schemeStats,
            'crew_lists' => $crewLists,
        ];
    }

    /**
     * Where the top cut stands, for the organizer's Rounds tab.
     *
     * @return array{size: int|null, started: bool, available_sizes: list<int>, block_reason: string|null}
     */
    private function cutState(Tournament $tournament): array
    {
        $cut = app(TournamentCutService::class);

        return [
            'size' => $tournament->cut_size,
            'started' => $tournament->rounds()->where('is_cut', true)->exists(),
            'available_sizes' => $cut->availableSizes($tournament),
            'block_reason' => $cut->blockReason($tournament),
        ];
    }

    /**
     * Side awards in display order. The public sees only those that have a recipient; organizers see them all.
     *
     * @return list<array{id: int, name: string, note: string|null, player_id: int|null, player_name: string|null}>
     */
    private function awardList(Tournament $tournament, bool $includeUnassigned): array
    {
        $awards = $tournament->awards()->with('player')->get();
        if (! $includeUnassigned) {
            $awards = $awards->filter(fn (TournamentAward $a) => $a->player_id !== null);
        }

        return $awards->map(fn (TournamentAward $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'note' => $a->note,
            'player_id' => $a->player_id,
            'player_name' => $a->player?->display_name,
        ])->values()->all();
    }

    /**
     * The organizer's table notes, keyed by table number (empty for rulesets without table details).
     *
     * @return array<int, array{number: int, name: string|null, description: string|null, image_url: string|null}>
     */
    private function tableDetails(Tournament $tournament): array
    {
        if (! $tournament->rulesetDefinition()->supportsTableDetails) {
            return [];
        }

        return $tournament->tables()->get()->mapWithKeys(fn (TournamentTable $t) => [$t->number => [
            'number' => $t->number,
            'name' => $t->name,
            'description' => $t->description,
            'image_url' => $t->image_url,
        ]])->all();
    }

    /**
     * Masters grouped by name with their titles — only used in the score-entry dialog, so deferred under its own group.
     */
    private function mastersDeferred(): DeferProp
    {
        return Inertia::defer(function () {
            $characters = Character::standard()->where('station', 'master')
                ->where('is_hidden', false)
                ->orderBy('name')->orderBy('title')
                ->get(['id', 'name', 'title', 'display_name', 'faction', 'second_faction']);

            $grouped = [];
            foreach ($characters->groupBy('name') as $name => $group) {
                $first = $group->first();
                $grouped[] = [
                    'name' => $name,
                    'faction' => $first->getRawOriginal('faction'),
                    'second_faction' => $first->getRawOriginal('second_faction'),
                    'titles' => $group->map(fn ($c) => [
                        'id' => $c->id,
                        'display_name' => $c->display_name,
                        'title' => $c->title,
                    ])->values(),
                ];
            }

            return $grouped; // @phpstan-ignore return.type
        }, 'masters');
    }

    /**
     * Props for the scoring desk: the pairings of every round with the score-entry fields, the standings and the
     * ruleset. Nothing from the organizer's setup tools (players' accounts, RSVPs, crews, reference data).
     *
     * @return array<string, mixed>
     */
    public function forScoring(Tournament $tournament): array
    {
        $tournament->load([
            'players',
            'rounds.games.playerOne:id,display_name,faction,user_id',
            'rounds.games.playerTwo:id,display_name,faction,user_id',
            'rounds.games.trackerGame',
        ]);

        return [
            'tournament' => $tournament,
            'ruleset' => $this->ruleset($tournament),
            'standings' => $this->standings->compute($tournament),
            'tables' => $this->tableDetails($tournament),
            // Leaders (master + title) are recorded in rulesets that track them, so the desk needs the same picker data.
            'masters' => $tournament->rulesetDefinition()->tracksLeaders ? $this->mastersDeferred() : null,
        ];
    }

    /**
     * Props for the organizer's Manage page (the heavy reference data is deferred).
     *
     * @return array<string, mixed>
     */
    public function forManage(Tournament $tournament): array
    {
        $tournament->load([
            'players.user:id,name,meta_id',
            'players.user.meta:id,name',
            'players.meta:id,name',
            'players.allegiance:id,slug,name',
            'players.garrison:id,name,share_code,is_public',
            'rounds.games.playerOneCrewBuild.master:id,name,display_name',
            'rounds.games.playerTwoCrewBuild.master:id,name,display_name',
            'players.members.user:id,name',
            'players.members.allegiance:id,slug,name',
            'players.members.garrison:id,name,share_code,is_public',
            'rsvps.user:id,name',
            'rsvps.allegiance:id,slug,name',
            'rounds.games.playerOne:id,display_name,faction,user_id',
            'rounds.games.playerTwo:id,display_name,faction,user_id',
            // Don't column-restrict trackerGame — Game appends `season_label`,
            // whose accessor reads `$this->season`. A restricted select that
            // omits `season` crashes under strict-attribute mode the moment
            // Inertia serializes the model. Same comment as `view()`.
            'rounds.games.trackerGame',
            'rounds.strategy:id,name',
            'organizers:id,name',
            'scorers:id,name',
        ]);

        $standings = $this->standings->compute($tournament);
        $this->hidePrivateGarrisonCodes($tournament);
        $this->markWaitlist($tournament);
        $this->markCrewLists($tournament);

        $seasons = collect(PoolSeasonEnum::casesNewestFirst())->map(fn (PoolSeasonEnum $s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ]);

        // Reference data needed for editing (scenario picker, score dialog, master
        // dropdown) — deferred so the page renders immediately and these batch
        // in a follow-up request. Grouped under "scenario" so the Inertia client
        // fires one extra request for all three instead of three separate ones.
        $strategiesDeferred = Inertia::defer(function () use ($tournament) {
            /** @var \App\Enums\PoolSeasonEnum $season */
            $season = $tournament->season;
            $results = [];
            foreach (Strategy::forSeason($season)->orderBy('name')->get() as $s) {
                $results[] = ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'image_url' => $s->image_url];
            }

            return $results;
        }, 'scenario');

        $schemesDeferred = Inertia::defer(function () use ($tournament) {
            /** @var \App\Enums\PoolSeasonEnum $season */
            $season = $tournament->season;
            $results = [];
            foreach (Scheme::forSeason($season)->orderBy('name')->get() as $s) {
                $results[] = ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'image_url' => $s->image_url, 'prerequisite' => $s->prerequisite, 'reveal' => $s->reveal, 'scoring' => $s->scoring];
            }

            return $results;
        }, 'scenario');

        $mastersDeferred = $this->mastersDeferred();

        return [
            'tournament' => $tournament,
            'ruleset' => $this->ruleset($tournament),
            'tables' => $this->tableDetails($tournament),
            'awards' => $this->awardList($tournament, true),
            'cut' => $this->cutState($tournament),
            // Whether a Discord channel is connected; the webhook URL itself never leaves the server.
            'discord_connected' => (bool) $tournament->discord_webhook_url,
            'allegiances' => $this->allegianceOptions($tournament),
            'standings' => $standings,
            'seasons' => $seasons,
            'factions' => fn () => FactionEnum::buildDetails(),
            'encounter_types' => collect(EncounterTypeEnum::cases())->map(fn ($e) => ['value' => $e->value, 'label' => $e->label()]),
            'tos_operations' => $tournament->rulesetDefinition()->usesScenarioField('tos_operation')
                ? Operation::orderBy('sort_order')->orderBy('name')->get()->map(fn (Operation $o) => $this->operationPayload($o))->values()
                : [],
            'tos_deployments' => $tournament->rulesetDefinition()->usesScenarioField('tos_deployment')
                ? Deployment::orderBy('sort_order')->orderBy('name')->get()->map(fn (Deployment $d) => $this->deploymentPayload($d))->values()
                : [],
            'all_deployments' => collect(DeploymentEnum::cases())->map(fn (DeploymentEnum $d) => ['value' => $d->value, 'label' => $d->label(), 'description' => $d->description(), 'image_url' => $d->imageUrl()]),
            // Deferred below — page renders without these, a follow-up request
            // fills them in. Grouping means strategies + schemes batch into one
            // request, masters into another.
            'all_strategies' => $strategiesDeferred,
            'all_schemes' => $schemesDeferred,
            'masters' => $mastersDeferred,
        ];
    }

    /**
     * The tournament's ruleset descriptor, with `scoring`/`tiebreakers` replaced by the EFFECTIVE values
     * (ruleset defaults + this tournament's overrides) so the UI never has to re-derive them.
     *
     * @return array<string, mixed>
     */
    public function ruleset(Tournament $tournament): array
    {
        return [
            ...$tournament->rulesetDefinition()->toArray(),
            'default_scoring' => $tournament->rulesetDefinition()->scoring->toArray(),
            // One leader per side makes no sense when a side is a team.
            'tracks_leaders' => $tournament->rulesetDefinition()->tracksLeaders && ! $tournament->isTeamEvent(),
            'default_tiebreakers' => $tournament->rulesetDefinition()->tiebreakers,
            'active_variants' => $tournament->activeVariants(),
            // True when the TO changed the format's scoring (shown to players so nobody is surprised).
            'scoring_customized' => $this->scoringIsCustomized($tournament),
            'scoring' => $tournament->scoringConfig()->toArray(),
            'tiebreakers' => $tournament->tiebreakerKeys(),
        ];
    }

    /**
     * Allegiances a player can declare, only for rulesets that use them.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, slug: string, name: string}>
     */
    public function allegianceOptions(Tournament $tournament): \Illuminate\Support\Collection
    {
        if ($tournament->rulesetDefinition()->declaration !== 'allegiance') {
            return collect();
        }

        return Allegiance::orderBy('name')->get(['id', 'slug', 'name'])
            ->map(fn (Allegiance $a) => ['id' => $a->id, 'slug' => $a->slug, 'name' => $a->name])->values();
    }

    /**
     * Resolve each round's TOS scenario ids to display payloads.
     *
     * @param  iterable<TournamentRound>  $rounds
     * @return array<int, array{operation: array<string, mixed>|null, deployment: array<string, mixed>|null}>
     */
    private function resolveTosScenarios(iterable $rounds): array
    {
        $rounds = collect($rounds);
        $operations = Operation::whereIn('id', $rounds->map(fn ($r) => $r->scenario['operation_id'] ?? null)->filter()->unique())->get()->keyBy('id');
        $deployments = Deployment::whereIn('id', $rounds->map(fn ($r) => $r->scenario['deployment_id'] ?? null)->filter()->unique())->get()->keyBy('id');

        $out = [];
        foreach ($rounds as $round) {
            $op = $operations->get($round->scenario['operation_id'] ?? null);
            $dep = $deployments->get($round->scenario['deployment_id'] ?? null);
            if ($op || $dep) {
                $out[$round->id] = [
                    'operation' => $op ? $this->operationPayload($op) : null,
                    'deployment' => $dep ? $this->deploymentPayload($dep) : null,
                ];
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function operationPayload(Operation $o): array
    {
        return [
            'id' => $o->id,
            'name' => $o->name,
            'source' => $o->source,
            'flavor' => $o->flavor,
            'additional_setup' => $o->additional_setup,
            'special_rules' => $o->special_rules,
            'victory_conditions' => $o->victory_conditions,
            'image_url' => $o->image_path ? '/storage/'.$o->image_path : null,
        ];
    }

    /** @return array<string, mixed> */
    private function deploymentPayload(Deployment $d): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'suit' => $d->suit,
            'description' => $d->description,
            'image_url' => $d->image_path ? '/storage/'.$d->image_path : null,
        ];
    }

    /** Whether the event's effective scoring or tiebreaker order differs from its ruleset's (legacy bye columns equal to the default don't count). */
    private function scoringIsCustomized(Tournament $tournament): bool
    {
        $ruleset = $tournament->rulesetDefinition();
        $scoringDiffers = $tournament->scoringConfig()->toArray() !== $ruleset->scoring->toArray();
        $override = ((array) ($tournament->scoring_config ?? []))['tiebreakers'] ?? null;

        return $scoringDiffers || (is_array($override) && $override !== [] && array_values($override) !== $ruleset->tiebreakers);
    }

    /**
     * The view/display pages are public and serialise whole models, so strip what only the people involved should see:
     * the players' report details (score proposal, dispute note) go to the two sides and the organizers, and nothing
     * public needs crew-build ids, tracker settings/loot state, or players' meta/profile columns.
     */
    private function redactPublicPayload(Tournament $tournament): void
    {
        $viewer = request()->user();
        $isOrganizer = $viewer !== null && $viewer->can('manage', $tournament);
        $playersById = $tournament->players->keyBy('id');

        foreach ($tournament->players as $player) {
            $player->makeHidden('meta');
            $player->user?->makeHidden(['meta', 'meta_id']);
        }

        foreach ($tournament->rounds as $round) {
            foreach ($round->games as $game) {
                $game->makeHidden(['player_one_crew_build_id', 'player_two_crew_build_id']);

                $involved = $isOrganizer || ($viewer !== null && collect([$game->player_one_id, $game->player_two_id])
                    ->filter()
                    ->contains(fn (int $id) => in_array($viewer->id, $playersById->get($id)?->linkedUserIds() ?? [], true)));
                if (! $involved) {
                    $game->makeHidden(['report_note', 'reported_score', 'reported_by_player_id', 'reported_at']);
                }

                $game->trackerGame?->makeHidden(['settings', 'loot_state', 'creator_id']);
            }
        }
    }

    /** A Garrison is public information at the event, but its share link only works when the owner made it public. */
    /** Whether this event asks for Crew Builder lists: individual play under a faction-declaring ruleset. */
    private function usesCrewLists(Tournament $tournament): bool
    {
        return ! $tournament->isTeamEvent() && $tournament->rulesetDefinition()->declaration === 'faction';
    }

    /**
     * Organizer view: the crew each side brings to each pairing, with its flags. The build itself stays out of the payload;
     * only this summary (master, size, share link, problems) is sent.
     */
    private function markCrewLists(Tournament $tournament): void
    {
        $uses = $this->usesCrewLists($tournament);
        foreach ($tournament->rounds as $round) {
            foreach ($round->games as $game) {
                $game->setAttribute('crew_one', $uses ? CrewListCheck::summarize($tournament, $game->playerOneCrewBuild, $game->player_one_faction) : null);
                $game->setAttribute('crew_two', $uses ? CrewListCheck::summarize($tournament, $game->playerTwoCrewBuild, $game->player_two_faction) : null);
                $game->makeHidden(['playerOneCrewBuild', 'playerTwoCrewBuild']);
            }
        }
    }

    /**
     * What a signed-in player needs to bring a crew to their current game: their own saved crews, and the one already
     * attached. Crews change every round, so this is about the game in the round being set up or played. Null for
     * everyone else (guests, spectators, byes, events that don't use crew lists).
     *
     * @return array<string, mixed>|null
     */
    private function crewListsFor(Tournament $tournament): ?array
    {
        $viewer = request()->user();
        if ($viewer === null || ! $this->usesCrewLists($tournament)) {
            return null;
        }

        $player = $tournament->players->first(fn ($p) => in_array($viewer->id, $p->linkedUserIds(), true));
        if ($player === null || $player->dropped_after_round !== null) {
            return null;
        }

        $current = null;
        foreach ($tournament->rounds->sortByDesc('round_number') as $round) {
            if ($round->status === TournamentRoundStatusEnum::Completed) {
                continue;
            }
            $game = $round->games->first(fn ($g) => ! $g->is_bye && $g->player_two_id !== null && ($g->player_one_id === $player->id || $g->player_two_id === $player->id));
            if ($game !== null) {
                $current = [$round, $game];
                break;
            }
        }
        if ($current === null) {
            return null;
        }
        [$round, $game] = $current;
        $side = $game->player_one_id === $player->id ? 'one' : 'two';
        $selected = $game->getRawOriginal("player_{$side}_crew_build_id");

        $builds = CrewBuild::where('user_id', $viewer->id)->where('is_archived', false)->with('master:id,display_name')->orderBy('name')->get()
            ->map(fn (CrewBuild $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'faction' => $b->faction->value,
                'faction_label' => $b->faction->label(),
                'master' => data_get($b, 'master.display_name'),
                'encounter_size' => (int) $b->encounter_size,
            ])->values();

        return [
            'game_id' => $game->id,
            'round_number' => $round->round_number,
            'required' => (bool) $tournament->require_crew_lists,
            'open' => $game->result === TournamentGameResultEnum::Pending,
            'selected_id' => $selected !== null ? (int) $selected : null,
            'declared_faction' => $game->getRawOriginal("player_{$side}_faction") ?? $player->getRawOriginal('faction'),
            'builds' => $builds,
        ];
    }

    /** Tell each RSVP where it stands under the player limit (null = has a seat). */
    private function markWaitlist(Tournament $tournament): void
    {
        $positions = $tournament->waitlistPositions();
        foreach ($tournament->rsvps as $rsvp) {
            $rsvp->setAttribute('waitlist_position', $positions[$rsvp->id] ?? null);
        }
    }

    private function hidePrivateGarrisonCodes(Tournament $tournament): void
    {
        foreach ($tournament->players as $player) {
            foreach ([$player, ...$player->members] as $competitor) {
                if ($competitor->garrison && ! $competitor->garrison->is_public) {
                    $competitor->garrison->makeHidden('share_code');
                }
            }
        }
    }
}
