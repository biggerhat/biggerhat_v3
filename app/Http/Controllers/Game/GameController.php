<?php

namespace App\Http\Controllers\Game;

use App\Enums\DeploymentEnum;
use App\Enums\FactionEnum;
use App\Enums\GameRoleEnum;
use App\Enums\GameShowContext;
use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use App\Events\GamePlayerJoined;
use App\Events\GameStatusChanged;
use App\Events\TournamentUpdated;
use App\Http\Controllers\Concerns\BuildsPageMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Games\StoreGameRequest;
use App\Http\Requests\Games\UpdateGameSettingsRequest;
use App\Http\Requests\Games\UpdateScenarioRequest;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Token;
use App\Models\User;
use App\Notifications\Game\GameOpponentJoined;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;
use Inertia\ResponseFactory;

class GameController extends Controller
{
    use BuildsPageMeta;

    public function index(): Response|ResponseFactory
    {
        $userId = Auth::id();

        return inertia('Games/Index', [
            'active_games' => Game::forUser($userId)->active()
                ->with(['players.user:id,name', 'strategy:id,name'])
                ->latest()
                ->get(),
            'recent_games' => Game::forUser($userId)->completed()
                ->with(['players.user:id,name', 'strategy:id,name', 'winner:id,name'])
                ->latest('completed_at')
                ->take(20)
                ->get(),
            'observable_games' => $this->observableGamesList(),
        ]);
    }

    public function publicIndex(): Response|ResponseFactory
    {
        return inertia('Games/Index', [
            'active_games' => [],
            'recent_games' => [],
            'observable_games' => $this->observableGamesList(),
        ]);
    }

    /** Shared list of recently-active public games (index + publicIndex). */
    private function observableGamesList()
    {
        return Game::observable()
            ->with(['players.user:id,name', 'strategy:id,name', 'winner:id,name'])
            ->latest('updated_at')
            ->take(10)
            ->get();
    }

    public function create(): Response|ResponseFactory
    {
        $seasons = collect(PoolSeasonEnum::cases())->map(fn (PoolSeasonEnum $s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ]);

        // Campaign format is never offered here, regardless of Campaign Mode
        // access — a standalone Campaign-format Game with no campaign_games
        // wrapper isn't meaningful (its crew can only ever be guessed after
        // the fact). Campaign games always start from the Campaign hub
        // instead (CampaignGameController::store()/playLive()), which
        // creates the campaign_games link eagerly and unambiguously.
        $formats = collect(\App\Enums\GameFormatEnum::cases())
            ->reject(fn (\App\Enums\GameFormatEnum $f) => $f === \App\Enums\GameFormatEnum::Campaign)
            ->map(fn (\App\Enums\GameFormatEnum $f) => [
                'value' => $f->value,
                'label' => $f->label(),
                'default_encounter_size' => $f->defaultEncounterSize(),
                'uses_scenario' => $f->usesScenario(),
            ])
            ->values();

        return inertia('Games/Create', [
            'seasons' => $seasons,
            'encounter_sizes' => [35, 40, 50],
            'formats' => $formats,
        ]);
    }

    public function store(StoreGameRequest $request)
    {
        $validated = $request->validated();

        $seasonEnum = PoolSeasonEnum::from($validated['season']);
        $format = \App\Enums\GameFormatEnum::tryFrom($validated['format'] ?? '') ?? \App\Enums\GameFormatEnum::Standard;

        // Bonanza Brawl skips scenario generation — it has no Strategy / Scheme
        // pool / Deployment, scoring is event-driven VP, and encounter size is
        // fixed at 11ss for a single-model crew. Standard generates the usual
        // scenario triple.
        $strategy = null;
        $deployment = null;
        $schemePool = null;
        if ($format->usesScenario()) {
            $strategies = Strategy::forSeason($seasonEnum)->get();
            $schemes = Scheme::forSeason($seasonEnum)->get();
            $deployments = DeploymentEnum::cases();

            $strategy = $strategies->isNotEmpty() ? $strategies->random() : null;
            $deployment = $deployments[array_rand($deployments)];
            $schemePool = $schemes->count() >= 3
                ? $schemes->random(3)->pluck('id')->toArray()
                : $schemes->pluck('id')->toArray();
        }

        // Bonanza Brawl is forced into solo mode — the rulebook describes a 3-8
        // player FFA, so the tracker isn't trying to model the table; it's a
        // personal scratchpad where the user tracks their own model's HP, loot
        // attachments, and VP. Slot 2 stays inert (created so existing
        // firstWhere('slot', 2) callsites don't blow up) but never surfaces.
        $isSolo = $format === \App\Enums\GameFormatEnum::BonanzaBrawl
            ? true
            : filter_var($request->input('is_solo', false), FILTER_VALIDATE_BOOLEAN);

        // Bonanza locks encounter_size to its rule-defined value regardless of
        // what the form sent — the format spec is authoritative.
        $encounterSize = $format === \App\Enums\GameFormatEnum::BonanzaBrawl
            ? \App\Enums\GameFormatEnum::BonanzaBrawl->defaultEncounterSize()
            : $validated['encounter_size'];

        $game = Game::create([
            'name' => $validated['name'] ?? null,
            'encounter_size' => $encounterSize,
            'season' => $seasonEnum->value,
            'format' => $format->value,
            'strategy_id' => $strategy?->id,
            'deployment' => $deployment?->value,
            'scheme_pool' => $schemePool,
            'status' => $isSolo ? GameStatusEnum::FactionSelect : GameStatusEnum::Setup,
            'started_at' => $isSolo ? now() : null,
            'creator_id' => Auth::id(),
            'is_solo' => $isSolo,
        ]);

        // Randomly assign roles
        $roles = collect([GameRoleEnum::Attacker->value, GameRoleEnum::Defender->value])->shuffle();

        // Creator is player 1
        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => Auth::id(),
            'slot' => 1,
            'role' => $isSolo ? $roles[0] : null,
        ]);

        if ($isSolo) {
            // Create opponent player (no user) for solo mode
            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => null,
                'slot' => 2,
                'opponent_name' => 'Opponent',
                'role' => $roles[1],
            ]);
        }

        return redirect()->route('games.show', $game->uuid);
    }

    public function show(Game $game): Response|ResponseFactory
    {
        $this->authorize('view', $game);

        $game->load($this->eagerLoadsFor($game, GameShowContext::SelfView));
        $this->ensureCrewReferences($game);

        return inertia('Games/Show', $this->buildShowProps($game, GameShowContext::SelfView));
    }

    /**
     * Relations to eager-load for a Games/Show render, by audience. Participants
     * see crews from Scheme Select onward and use a lean turn select during live
     * play (excludes the large crew_snapshot JSON); public observers and the
     * summary load full turn snapshots once a game has reached gameplay.
     *
     * @return array<int, string>
     */
    private function eagerLoadsFor(Game $game, GameShowContext $context): array
    {
        $loads = ['players.user:id,name', 'strategy.tokens'];
        $postSetup = [GameStatusEnum::InProgress, GameStatusEnum::Completed, GameStatusEnum::Abandoned];

        // Summary is only reachable for finished games and always wants the full
        // crew + turn snapshots.
        if ($context === GameShowContext::Summary) {
            return array_merge($loads, [
                'players.crewMembers.customCharacter:id,faction,actions,abilities,display_name,front_image,back_image',
                'players.crewBuild',
                'players.master.crewUpgrades',
                'players.turns',
                'winner:id,name',
            ]);
        }

        if ($context === GameShowContext::SelfView) {
            // Participants also need crews during Scheme Select.
            if (in_array($game->status, [GameStatusEnum::SchemeSelect, ...$postSetup])) {
                $loads[] = 'players.crewMembers.customCharacter:id,faction,actions,abilities,display_name,front_image,back_image';
                $loads[] = 'players.crewBuild';
                $loads[] = 'players.master.crewUpgrades';
            }
            if ($game->status === GameStatusEnum::InProgress) {
                $loads[] = 'players.turns:id,game_player_id,turn_number,strategy_points,strategy_bonus_used,scheme_points,scheme_id,scheme_action,scheme_notes,next_scheme_id,points_scored';
            } elseif (in_array($game->status, [GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
                $loads[] = 'players.turns';
            }
        } elseif (in_array($game->status, $postSetup)) {
            // Observer: crews + full turns once gameplay has started.
            $loads[] = 'players.crewMembers.customCharacter:id,faction,actions,abilities,display_name,front_image,back_image';
            $loads[] = 'players.crewBuild';
            $loads[] = 'players.master.crewUpgrades';
            $loads[] = 'players.turns';
        }

        if (in_array($game->status, [GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            $loads[] = 'winner:id,name';
        }

        return $loads;
    }

    /**
     * Null out faction / master picks that must stay hidden until simultaneous
     * reveal. During FactionSelect / MasterSelect a pick stays hidden until BOTH
     * players have locked in; without this the first to submit leaks their pick
     * via the Inertia payload. Participants hide only the opponent's unrevealed
     * pick; public observers hide both players'; the summary (finished game)
     * hides nothing.
     */
    private function applyHiddenPicks(Game $game, GameShowContext $context): void
    {
        if ($game->is_solo || $context === GameShowContext::Summary) {
            return;
        }

        // Observer matches no slot ($mySlot === null), so every player is hidden.
        $mySlot = $context === GameShowContext::SelfView
            ? $game->players->firstWhere('user_id', Auth::id())?->slot
            : null;
        $hidden = fn (GamePlayer $player) => $player->slot !== $mySlot;

        if ($game->status === GameStatusEnum::FactionSelect) {
            foreach ($game->players as $player) {
                if ($hidden($player)) {
                    $player->setAttribute('faction', null);
                }
            }
        }

        if ($game->status === GameStatusEnum::MasterSelect) {
            foreach ($game->players as $player) {
                if ($hidden($player)) {
                    $player->setAttribute('master_name', null);
                    $player->setAttribute('master_id', null);
                }
            }
        }
    }

    /**
     * Full reachable scheme tree for the pool: every pool scheme plus all of
     * their follow-ups, recursively. Seen-set + index cursor keeps it O(n)
     * instead of the O(n²) an array_shift queue would cost.
     *
     * @param  \Illuminate\Support\Collection<int, Scheme>  $schemeCache
     * @param  array<int, int>  $poolOrder
     * @return array<int, array<string, mixed>>
     */
    private function reachableSchemes(\Illuminate\Support\Collection $schemeCache, array $poolOrder): array
    {
        $seen = array_flip($poolOrder);
        $queue = array_values($poolOrder);
        for ($qi = 0; $qi < count($queue); $qi++) {
            $scheme = $schemeCache->get($queue[$qi]);
            if (! $scheme) {
                continue;
            }
            foreach ([$scheme->next_scheme_one_id, $scheme->next_scheme_two_id, $scheme->next_scheme_three_id] as $nextId) {
                if ($nextId && ! isset($seen[$nextId])) {
                    $seen[$nextId] = true;
                    $queue[] = $nextId;
                }
            }
        }
        $reachableIds = collect(array_keys($seen));

        return $schemeCache->filter(fn (Scheme $s) => $reachableIds->contains($s->id))
            ->values()
            ->map(fn (Scheme $s) => $s->toTrackerArray())
            ->toArray();
    }

    /**
     * Token catalog visibility by audience: participants only during live play;
     * observers during live play and after; the summary always.
     *
     * @return \Illuminate\Support\Collection<int, Token>
     */
    private function buildTokensProp(Game $game, GameShowContext $context): \Illuminate\Support\Collection
    {
        $needsTokens = match ($context) {
            GameShowContext::SelfView => $game->status === GameStatusEnum::InProgress,
            GameShowContext::Observer => in_array($game->status, [GameStatusEnum::InProgress, GameStatusEnum::Completed]),
            GameShowContext::Summary => true,
        };

        return $needsTokens
            ? Token::orderBy('name')->get(['id', 'name', 'slug', 'description'])
            : collect();
    }

    /** Current/held schemes visible to public observers during live or finished play. */
    private function buildObserverCurrentSchemes(Game $game): array
    {
        if (! in_array($game->status, [GameStatusEnum::InProgress, GameStatusEnum::Completed])) {
            return [];
        }
        $schemeIds = $game->players->pluck('current_scheme_id')->filter()->unique()->values();
        if ($game->status === GameStatusEnum::Completed) {
            $turnSchemeIds = GameTurn::where('game_id', $game->id)
                ->whereNotNull('scheme_id')
                ->pluck('scheme_id')
                ->unique();
            $schemeIds = $schemeIds->merge($turnSchemeIds)->unique()->values();
        }

        return Scheme::whereIn('id', $schemeIds)->get()->map(fn (Scheme $s) => $s->toTrackerArray())->toArray();
    }

    /** Every scheme touched across a finished game — current picks + each turn's scheme. */
    private function buildSummaryCurrentSchemes(Game $game): array
    {
        $schemeIds = $game->players->pluck('current_scheme_id')->filter()->unique()->values();
        $turnSchemeIds = GameTurn::where('game_id', $game->id)
            ->whereNotNull('scheme_id')
            ->pluck('scheme_id')
            ->unique();
        $allSchemeIds = $schemeIds->merge($turnSchemeIds)->unique()->values();

        return Scheme::whereIn('id', $allSchemeIds)->get()->map(fn (Scheme $s) => $s->toTrackerArray())->toArray();
    }

    /**
     * Single source of truth for the Games/Show Inertia payload, shared by
     * show() (participant), observe() and summary(). Every per-audience
     * divergence — pick reveal, editor data, scheme intel, the observer flag and
     * the self-only overlays — is isolated here. See {@see GameShowContext}.
     *
     * @return array<string, mixed>
     */
    private function buildShowProps(Game $game, GameShowContext $context): array
    {
        $this->applyHiddenPicks($game, $context);
        $game->strategy?->append('image_url');

        $isSelf = $context->isSelf();

        // Season scheme cache (small table) powers the pool, reachable tree and
        // participant scheme intel.
        $schemePoolOrder = $game->scheme_pool ?? [];
        $schemeCache = Scheme::forSeason($game->season)->get()->keyBy('id');
        $schemes = $schemeCache
            ->filter(fn (Scheme $s) => in_array($s->id, $schemePoolOrder))
            ->sortBy(fn (Scheme $s) => array_search($s->id, $schemePoolOrder))
            ->values()
            ->map(fn (Scheme $s) => $s->toTrackerArray());

        // Scenario editor data — only the participant who can edit gets it; the
        // gate returns [] for every other audience, so the closures are safe to
        // share.
        $canEditScenario = fn () => $isSelf && (Auth::user()?->can('updateScenario', $game) ?? false);
        $allStrategies = fn () => $canEditScenario()
            ? Strategy::forSeason($game->season)->orderBy('name')->get()->map(fn (Strategy $s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'image_url' => $s->image_url])
            : [];
        $allSchemes = fn () => $canEditScenario()
            ? Scheme::forSeason($game->season)->orderBy('name')->get()->map(fn (Scheme $s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'image_url' => $s->image_url])
            : [];
        $allDeployments = fn () => $canEditScenario()
            ? collect(DeploymentEnum::cases())->map(fn (DeploymentEnum $d) => ['value' => $d->value, 'label' => $d->label(), 'image_url' => $d->imageUrl()])
            : [];

        $props = [
            'game' => $game,
            'schemes' => $schemes,
            'all_reachable_schemes' => $isSelf ? $this->reachableSchemes($schemeCache, $schemePoolOrder) : [],
            'deployment' => $game->deployment ? (function () use ($game) {
                /** @var DeploymentEnum $d */
                $d = $game->deployment;

                return [
                    'value' => $d->value,
                    'label' => $d->label(),
                    'description' => $d->description(),
                    'image_url' => $d->imageUrl(),
                ];
            })() : null,
            'factions' => fn () => $isSelf ? FactionEnum::buildDetails() : [],
            'masters' => fn () => $isSelf ? $this->buildMastersProp($game) : [],
            'my_crews' => fn () => $isSelf ? $this->buildMyCrewsProp($game) : [],
            'all_strategies' => $allStrategies,
            'all_schemes' => $allSchemes,
            'all_deployments' => $allDeployments,
            'all_markers' => fn () => $isSelf && in_array($game->status, [GameStatusEnum::InProgress, GameStatusEnum::SchemeSelect])
                ? \App\Models\Marker::orderBy('name')->get(['id', 'name', 'slug'])
                : [],
            'tokens' => fn () => $this->buildTokensProp($game, $context),
            'character_upgrades' => fn () => $isSelf && $game->status === GameStatusEnum::InProgress
                ? $this->buildCharacterUpgradesProp($game)
                : [],
            'current_schemes' => match ($context) {
                GameShowContext::SelfView => fn () => $this->buildCurrentSchemesProp($game),
                GameShowContext::Observer => fn () => $this->buildObserverCurrentSchemes($game),
                GameShowContext::Summary => $this->buildSummaryCurrentSchemes($game),
            },
            'opponent_scheme_intel' => fn () => $isSelf ? $this->buildOpponentSchemeIntel($game, $schemeCache) : null,
            // next_schemes and opponent_next_schemes read directly from stored scheme_pool
            'next_schemes' => function () use ($game, $schemeCache, $isSelf) {
                if (! $isSelf || $game->status !== GameStatusEnum::InProgress) {
                    return [];
                }
                $myPlayer = $game->players->first(fn ($p) => $p->user_id === Auth::id());
                if (! $myPlayer) {
                    return [];
                }

                return self::schemesFromCache($schemeCache, $myPlayer->scheme_pool ?? []);
            },
            'opponent_next_schemes' => function () use ($game, $schemeCache, $isSelf) {
                if (! $isSelf || ! $game->is_solo || $game->status !== GameStatusEnum::InProgress) {
                    return [];
                }
                $opponent = $game->players->firstWhere('slot', 2);
                if (! $opponent) {
                    return [];
                }

                return self::schemesFromCache($schemeCache, $opponent->scheme_pool ?? []);
            },
            'observer_scheme_intel' => fn () => $context === GameShowContext::Observer ? $this->buildObserverSchemeIntel($game) : null,
            'starting_crews' => fn () => $this->getStartingCrews($game),
            'is_observer' => ! $isSelf,
        ];

        // Loot catalog: lazily loaded only for Bonanza in-progress games, used by
        // the side-picker dialog, the dropped-marker list and the loot entries in
        // attached_upgrades (all reference cards by id). Live audiences only — the
        // summary historically omits it; preserved here, revisit as a follow-up.
        if ($context !== GameShowContext::Summary) {
            $props['loot_card_catalog'] = fn () => ($game->format === \App\Enums\GameFormatEnum::BonanzaBrawl && $game->status === GameStatusEnum::InProgress)
                ? \App\Models\LootCard::with([
                    // Column lists mirror BonanzaLootDeckController's public reference
                    // page so the in-game side-picker can render the same full
                    // ActionCard/AbilityCard/LootTriggerDisplay detail, not just names.
                    'sideAActions:id,name,slug,type,is_signature,stone_cost,range,range_type,stat,stat_suits,stat_modifier,resisted_by,target_number,target_suits,damage,description',
                    'sideBActions:id,name,slug,type,is_signature,stone_cost,range,range_type,stat,stat_suits,stat_modifier,resisted_by,target_number,target_suits,damage,description',
                    'sideAActions.triggers:id,name,slug,suits,stone_cost,description',
                    'sideBActions.triggers:id,name,slug,suits,stone_cost,description',
                    'sideAAbilities:id,name,slug,suits,defensive_ability_type,costs_stone,description',
                    'sideBAbilities:id,name,slug,suits,defensive_ability_type,costs_stone,description',
                    'sideATriggers:id,name,slug,suits,stone_cost,description',
                    'sideBTriggers:id,name,slug,suits,stone_cost,description',
                ])->orderBy('id')->get()
                : [];
        }

        // Self-only overlays. bonanza_crew_upgrades pulls upgrades via the picked
        // character's keywords (the model often isn't a master, so the direct
        // master.crewUpgrades relation is empty); campaign_context only renders on
        // campaign-format games.
        if ($isSelf) {
            $props['bonanza_crew_upgrades'] = fn () => $this->buildBonanzaCrewUpgrades($game);
            $props['campaign_context'] = fn () => $this->buildCampaignContext($game);
            $props['campaign_arsenal'] = fn () => $this->buildCampaignArsenalProp($game);
            $props['campaign_owned_equipment'] = fn () => $this->buildCampaignEquipmentProp($game);
            $props['campaign_leader_option'] = fn () => $game->format === \App\Enums\GameFormatEnum::Campaign
                ? $this->campaignLeaderMasterOption($game)
                : null;
            $props['campaign_totem'] = fn () => $this->buildCampaignTotemProp($game);
        }

        return $props;
    }

    /**
     * Sibling prop carrying CR + ss-pool bonus + crew links for a campaign-
     * format game. Returns null on standard / bonanza so the Vue layer can
     * conditionally render the banner.
     */
    private function buildCampaignContext(Game $game): ?array
    {
        if ($game->format !== \App\Enums\GameFormatEnum::Campaign) {
            return null;
        }

        $wrap = \App\Models\Campaign\CampaignGame::query()
            ->where('base_game_id', $game->id)
            ->with([
                'campaign:id,name,current_week,length_weeks',
                'crewA:id,share_code,name,user_id,crew_card_effect_id,crew_card_front_image',
                'crewB:id,share_code,name,user_id,crew_card_effect_id,crew_card_front_image',
                'crewA.crewCardEffect.actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
                'crewA.crewCardEffect.abilities',
                'crewA.crewCardAdvancements.crewCardEffect.actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
                'crewA.crewCardAdvancements.crewCardEffect.abilities',
                'crewB.crewCardEffect.actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
                'crewB.crewCardEffect.abilities',
                'crewB.crewCardAdvancements.crewCardEffect.actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
                'crewB.crewCardAdvancements.crewCardEffect.abilities',
            ])
            ->first();

        if (! $wrap) {
            // Solo games created outside the campaign hub (no CampaignGame
            // link is ever created for them, unlike hub-started games) —
            // fall back to the same crew resolution Arsenal/Equipment props
            // already use, so at least the player's own crew card still
            // shows instead of the whole banner silently disappearing.
            return $this->buildCampaignContextForSoloFallback($game);
        }

        // Propagate the crew-card action pivot's signature flag onto the
        // serialized action, same as ArsenalSheetController. crewB is null
        // for a solo live game (CampaignGameController::playLive()) — a
        // CampaignGame row exists (base_game_id set) but there's no
        // opponent crew, unlike every other case this branch previously saw.
        $this->applyCrewCardSignatureFlags($wrap->crewA);
        if ($wrap->crewB) {
            $this->applyCrewCardSignatureFlags($wrap->crewB);
        }

        return [
            // The CampaignGame row's own id — lets the tracker POST
            // campaigns.aftermaths.start once the game finishes, without
            // which there was no way to reach the Aftermath wizard from a
            // finished Campaign game at all.
            'id' => $wrap->id,
            'campaign' => $wrap->campaign->only(['id', 'name', 'current_week', 'length_weeks']),
            'crew_a' => $wrap->crewA->only(['id', 'share_code', 'name', 'user_id']),
            'crew_b' => $wrap->crewB?->only(['id', 'share_code', 'name', 'user_id']),
            'cr_a' => $wrap->cr_a,
            'cr_b' => $wrap->cr_b,
            'ss_bonus_to_lower' => $wrap->ss_bonus_to_lower,
            'encounter_size' => $wrap->encounter_size,
            'week_number' => $wrap->week_number,
            // Crew Card effect (pg 17, 32, 54) — starter + any Tier-4 borrowed
            // effects. Not surfaced anywhere else in Game Tracker.
            'crew_a_card' => $this->campaignCrewCardPayload($wrap->crewA),
            'crew_b_card' => $wrap->crewB ? $this->campaignCrewCardPayload($wrap->crewB) : ['effect' => null, 'borrowed' => [], 'front_image' => null],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildCampaignContextForSoloFallback(Game $game): ?array
    {
        if (! $game->is_solo) {
            return null;
        }

        $campaignCrew = $this->resolveCampaignCrewForUser($game);
        if (! $campaignCrew) {
            return null;
        }

        $campaignCrew->load([
            'campaign:id,name,current_week,length_weeks',
            'crewCardEffect.actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
            'crewCardEffect.abilities',
            'crewCardAdvancements.crewCardEffect.actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
            'crewCardAdvancements.crewCardEffect.abilities',
        ]);
        $this->applyCrewCardSignatureFlags($campaignCrew);

        return [
            // No CampaignGame row exists for this fallback path (see the
            // doc comment above), so there's nothing to start an Aftermath
            // against — the Game Tracker's CTA stays hidden for null id.
            'id' => null,
            'campaign' => $campaignCrew->campaign->only(['id', 'name', 'current_week', 'length_weeks']),
            'crew_a' => $campaignCrew->only(['id', 'share_code', 'name', 'user_id']),
            // No CampaignGame link means no tracked opponent/CR/encounter
            // stats for this game — only the player's own crew card is
            // resolvable this way.
            'crew_b' => null,
            'cr_a' => 0,
            'cr_b' => 0,
            'ss_bonus_to_lower' => 0,
            'encounter_size' => 0,
            'week_number' => $campaignCrew->campaign->current_week,
            'crew_a_card' => $this->campaignCrewCardPayload($campaignCrew),
            'crew_b_card' => ['effect' => null, 'borrowed' => [], 'front_image' => null],
        ];
    }

    private function applyCrewCardSignatureFlags(\App\Models\Campaign\CampaignCrew $crew): void
    {
        $crew->crewCardEffect?->actions->each(
            fn ($a) => $a->is_signature = (bool) $a->pivot->is_signature_action, // @phpstan-ignore property.notFound (pivot from BelongsToMany)
        );
        $crew->crewCardAdvancements->each(
            fn ($adv) => $adv->crewCardEffect->actions->each(
                fn ($a) => $a->is_signature = (bool) $a->pivot->is_signature_action, // @phpstan-ignore property.notFound (pivot from BelongsToMany)
            ),
        );
    }

    /**
     * @return array{effect: array<string, mixed>|null, borrowed: array<int, array<string, mixed>>, front_image: string|null}
     */
    private function campaignCrewCardPayload(\App\Models\Campaign\CampaignCrew $crew): array
    {
        return [
            'effect' => $this->crewCardEffectPayload($crew->crewCardEffect),
            'borrowed' => $crew->crewCardAdvancements->map(fn (\App\Models\Campaign\CampaignCrewCardAdvancement $adv) => [
                'id' => $adv->id,
                'effect' => $this->crewCardEffectPayload($adv->crewCardEffect),
            ])->all(),
            // Combined generated card (starter + every held Tier-4 borrow,
            // including restriction qualifier text) — see CombinedCrewCardEffects.
            'front_image' => $crew->crew_card_front_image,
        ];
    }

    /**
     * Shapes either possible crew-card-effect source (the generic
     * `CampaignCrewCard` catalog or a real keyword-matched `Upgrade`) into
     * the identical wire shape the Game Tracker's CrewCardEffect interface
     * expects — a plain `toArray()` would leak the Upgrade model's many
     * unrelated admin/catalog columns into this payload.
     */
    private function crewCardEffectPayload(\App\Models\Campaign\CampaignCrewCard|\App\Models\Upgrade|null $effect): ?array
    {
        if (! $effect) {
            return null;
        }

        return [
            'id' => $effect->id,
            'name' => $effect->name,
            'body' => $effect->description,
            // Real per-card art only ever exists on an Upgrade-sourced effect —
            // the shared CampaignCrewCard catalog has no card art column.
            'front_image' => $effect instanceof \App\Models\Upgrade ? $effect->front_image : null,
            'actions' => $effect->actions,
            'abilities' => $effect->abilities,
        ];
    }

    /**
     * Campaign arsenal models shaped for the inline crew picker shown during
     * CrewSelect. Empty array on any non-Campaign game or wrong status.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCampaignArsenalProp(Game $game): array
    {
        if ($game->format !== \App\Enums\GameFormatEnum::Campaign
            || $game->status !== \App\Enums\GameStatusEnum::CrewSelect
            || ! Auth::check()) {
            return [];
        }

        $campaignCrew = $this->resolveCampaignCrewForUser($game);
        if (! $campaignCrew) {
            return [];
        }
        $leader = $campaignCrew->leader;

        $leaderKeywordSlugs = collect($leader !== null ? ($leader->keywords ?? []) : [])
            ->pluck('name')
            ->map(fn ($n) => \Illuminate\Support\Str::slug($n))
            ->toArray();

        // Resolves gained_lucky_miss_ids to names for display — mirrors
        // ArsenalSheetController, which surfaces the same label/injuries/
        // lucky_miss/gained_abilities fields for this identical model.
        $luckyMissNames = \App\Models\Campaign\LuckyMiss::query()->pluck('name', 'id');

        return \App\Models\Campaign\CampaignArsenalModel::query()
            ->where('campaign_crew_id', $campaignCrew->id)
            ->active()
            ->with(['character.keywords', 'character.characteristics', 'injuries.injury:id,name,description', 'gainedAbilities:id,name,description'])
            ->get()
            ->map(function (\App\Models\Campaign\CampaignArsenalModel $m) use ($leaderKeywordSlugs, $luckyMissNames) {
                $char = $m->character;
                $sharesKeyword = $char->keywords->pluck('slug')->intersect($leaderKeywordSlugs)->isNotEmpty();
                $isVersatile = $char->characteristics->pluck('name')->map(fn ($n) => strtolower($n))->contains('versatile');
                $isOok = ! $sharesKeyword && ! $isVersatile;

                return [
                    // The arsenal row's own id — not just character_id — so
                    // owning several copies of the same catalog Character
                    // (each its own CampaignArsenalModel row) can be selected
                    // and hired individually instead of collapsing into one
                    // shared toggle. See GameCrewSelectPanel.vue.
                    'id' => $m->id,
                    'character_id' => $m->character_id,
                    'name' => $char->display_name ?? $char->name,
                    'label' => $m->label,
                    'faction' => $char->getRawOriginal('faction'),
                    'station' => $char->getRawOriginal('station'),
                    'cost' => $char->cost ?? 0,
                    'effective_cost' => $isOok ? (($char->cost ?? 0) + 1) : ($char->cost ?? 0),
                    'is_ook' => $isOok,
                    'is_peon' => $m->is_peon,
                    'injuries' => $m->injuries
                        ->map(fn ($pivot) => $pivot->injury ? ['id' => $pivot->injury->id, 'name' => $pivot->injury->name] : null)
                        ->filter()
                        ->values()
                        ->all(),
                    'lucky_miss' => collect($m->gained_lucky_miss_ids ?? [])
                        ->map(fn ($id) => $luckyMissNames[$id] ?? null)
                        ->filter()
                        ->values()
                        ->all(),
                    'gained_abilities' => $m->gainedAbilities->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The crew's current Totem (if unlocked, Tier-3+), offered as an
     * equipment-assignment target alongside the Leader and hires at
     * CrewSelect. Null on any non-Campaign game, wrong status, or a crew
     * that hasn't unlocked a Totem yet.
     *
     * @return array{id: int, name: string}|null
     */
    private function buildCampaignTotemProp(Game $game): ?array
    {
        if ($game->format !== \App\Enums\GameFormatEnum::Campaign
            || $game->status !== \App\Enums\GameStatusEnum::CrewSelect
            || ! Auth::check()) {
            return null;
        }

        $campaignCrew = $this->resolveCampaignCrewForUser($game);
        $totem = $campaignCrew?->totem;

        return $totem ? ['id' => $totem->id, 'name' => $totem->name] : null;
    }

    /**
     * Upgrade catalog offered by the in-play "attach upgrade" editor. Campaign
     * games swap the standard catalog for the acting player's own earned
     * equipment (pg 19: "any equipment in your arsenal may be attached to any
     * model in your crew... without cost") — never the full campaign equipment
     * catalog, so a player can't attach equipment their crew hasn't earned.
     */
    private function buildCharacterUpgradesProp(Game $game): \Illuminate\Support\Collection
    {
        if ($game->format !== \App\Enums\GameFormatEnum::Campaign) {
            return \App\Models\Upgrade::standard()->forCharacters()->orderBy('name')
                ->get(['id', 'name', 'slug', 'front_image', 'back_image', 'type', 'plentiful', 'power_bar_count', 'description'])
                ->map(fn (\App\Models\Upgrade $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'slug' => $u->slug,
                    'front_image' => $u->front_image,
                    'back_image' => $u->back_image,
                    'type' => $u->type,
                    'plentiful' => $u->plentiful,
                    'power_bar_count' => $u->power_bar_count,
                    'description' => $u->description,
                ])
                ->values();
        }

        $campaignCrew = $this->resolveCampaignCrewForUser($game);
        if (! $campaignCrew) {
            return collect();
        }

        return collect(\App\Support\Campaign\AftermathCatalog::ownedEquipmentForAttachment($campaignCrew, $campaignCrew->leader));
    }

    /**
     * A user's CampaignCrew for a Campaign-format game — resolved via the
     * CampaignGame link (crewA/crewB by user_id), or by matching faction for
     * a solo game created outside the campaign hub (no CampaignGame link
     * exists in that case). Defaults to the current user; callers building
     * data for BOTH players in a game (e.g. references) pass the other
     * player's user_id explicitly.
     */
    private function resolveCampaignCrewForUser(Game $game, ?int $userId = null): ?\App\Models\Campaign\CampaignCrew
    {
        $userId ??= Auth::id();
        if (! $userId) {
            return null;
        }

        $campaignGame = \App\Models\Campaign\CampaignGame::query()
            ->where('base_game_id', $game->id)
            ->with(['crewA', 'crewB'])
            ->first();

        if ($campaignGame) {
            if ($campaignGame->crewA && $campaignGame->crewA->user_id === $userId) {
                return $campaignGame->crewA;
            }
            if ($campaignGame->crewB && $campaignGame->crewB->user_id === $userId) {
                return $campaignGame->crewB;
            }

            return null;
        }

        if (! $game->is_solo) {
            return null;
        }

        $player = $game->players->first(fn ($p) => $p->user_id === $userId);
        if (! $player?->faction) {
            return null;
        }

        return \App\Models\Campaign\CampaignCrew::query()
            ->where('user_id', $userId)
            ->where('faction', $player->getRawOriginal('faction'))
            ->latest('id')
            ->first();
    }

    /**
     * The crew's owned equipment (pg 19), offered for optional assignment to
     * a specific hire (or the Leader) during CrewSelect — same catalog the
     * in-play "attach upgrade" editor uses, just available earlier. Empty
     * array on any non-Campaign game or wrong status.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCampaignEquipmentProp(Game $game): array
    {
        if ($game->format !== \App\Enums\GameFormatEnum::Campaign
            || $game->status !== \App\Enums\GameStatusEnum::CrewSelect
            || ! Auth::check()) {
            return [];
        }

        $campaignCrew = $this->resolveCampaignCrewForUser($game);
        if (! $campaignCrew) {
            return [];
        }

        return \App\Support\Campaign\AftermathCatalog::ownedEquipmentForAttachment($campaignCrew, $campaignCrew->leader);
    }

    /**
     * Crew upgrades available to a Bonanza model: every Crew-domain upgrade
     * attached to any of the picked character's Keywords. Returns an empty
     * collection on standard-format games so the prop is always defined but
     * cheap to ignore.
     */
    private function buildBonanzaCrewUpgrades(Game $game): \Illuminate\Support\Collection
    {
        if ($game->format !== \App\Enums\GameFormatEnum::BonanzaBrawl) {
            return collect();
        }
        $myPlayer = $game->players->firstWhere('user_id', Auth::id())
            ?? $game->players->firstWhere('slot', 1);
        if (! $myPlayer || ! $myPlayer->master_id) {
            return collect();
        }

        $character = \App\Models\Character::with(['keywords.crewUpgrades'])->find($myPlayer->master_id);
        if (! $character) {
            return collect();
        }

        return $character->keywords
            ->flatMap(fn ($keyword) => $keyword->crewUpgrades)
            ->unique('id')
            ->values()
            ->map(fn ($upgrade) => $upgrade->only(['id', 'name', 'slug', 'front_image', 'back_image', 'type', 'plentiful', 'power_bar_count']));
    }

    public function join(Game $game)
    {
        // Require login — redirect back to this join URL after auth
        if (! Auth::check()) {
            session()->put('url.intended', route('games.join', $game->uuid));

            return redirect()->route('login')
                ->with('message', 'Please log in or create an account to join this game.')
                ->with('messageType', 'warning');
        }

        $userId = Auth::id();

        // Solo games cannot be joined
        if ($game->is_solo) {
            return redirect()->route('games.index');
        }

        // Already in game? Go to show
        if ($game->players()->where('user_id', $userId)->exists()) {
            return redirect()->route('games.show', $game->uuid);
        }

        // Creator can't join as player 2
        if ($game->creator_id === $userId) {
            return redirect()->route('games.show', $game->uuid);
        }

        // Game must be in setup and have an open slot
        if ($game->status !== GameStatusEnum::Setup || $game->isFull()) {
            return redirect()->route('games.index')
                ->withMessage('This game is no longer accepting players.');
        }

        // Randomly assign attacker/defender to both players
        $roles = collect([GameRoleEnum::Attacker->value, GameRoleEnum::Defender->value])->shuffle();
        $game->players()->where('slot', 1)->update(['role' => $roles[0]]);

        $player = GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $userId,
            'slot' => 2,
            'role' => $roles[1],
        ]);

        // Advance to faction select now that both players are in
        $game->update([
            'status' => GameStatusEnum::FactionSelect,
            'started_at' => now(),
        ]);

        broadcast(new GamePlayerJoined($game, $player->load('user')))->toOthers();
        broadcast(new GameStatusChanged($game))->toOthers();
        User::find($game->creator_id)?->notify(new GameOpponentJoined($game, $player->user->name));

        return redirect()->route('games.show', $game->uuid);
    }

    public function updateScenario(UpdateScenarioRequest $request, Game $game)
    {
        $validated = $request->validated();

        $game->update([
            'strategy_id' => $validated['strategy_id'],
            'deployment' => $validated['deployment'],
            'scheme_pool' => $validated['scheme_pool'],
        ]);

        return response()->json(['success' => true]);
    }

    public function regenerateScenario(Game $game)
    {
        $this->authorize('updateScenario', $game);

        $seasonEnum = $game->season;
        $strategies = Strategy::forSeason($seasonEnum)->get();
        $schemes = Scheme::forSeason($seasonEnum)->get();
        $deployments = DeploymentEnum::cases();

        $game->update([
            'strategy_id' => $strategies->isNotEmpty() ? $strategies->random()->id : null,
            'deployment' => $deployments[array_rand($deployments)]->value,
            'scheme_pool' => $schemes->count() >= 3
                ? $schemes->random(3)->pluck('id')->toArray()
                : $schemes->pluck('id')->toArray(),
        ]);

        // Return JSON instead of redirecting — the front-end uses raw fetch +
        // router.reload to refresh props, mirroring updateScenario. The old
        // 302→games.show redirect did not reliably trigger an Inertia prop
        // refresh, so the new scenario landed in the DB but the UI stayed stale.
        return response()->json(['success' => true]);
    }

    public function destroy(Game $game)
    {
        $this->authorize('view', $game);
        $userId = Auth::id();
        $player = $game->players()->where('user_id', $userId)->first();

        // If this game is linked to a TournamentGame, capture the parent
        // tournament before any mutation so we can still broadcast after the
        // row is gone. Hard-delete clears the relation; TournamentGame.game_id
        // will dangle until the TO or destroyForGame handles it.
        $trackerLinkedTournament = \App\Models\TournamentGame::with('round.tournament')
            ->where('game_id', $game->id)
            ->first()?->round?->tournament;

        // Solo games or games still in setup (no opponent yet): hard delete
        if ($game->is_solo || $game->players()->count() <= 1) {
            $game->delete();

            if ($trackerLinkedTournament) {
                broadcast(new TournamentUpdated($trackerLinkedTournament, 'tracker_deleted'))->toOthers();
            }

            return redirect()->route('games.index')
                ->withMessage('Game deleted.');
        }

        // Duel game: soft-hide for this player
        if ($player) {
            $player->update(['hidden_at' => now()]);
        }

        // If all players have hidden, hard delete
        $allHidden = $game->players()->whereNull('hidden_at')->count() === 0;
        if ($allHidden) {
            $game->delete();

            if ($trackerLinkedTournament) {
                broadcast(new TournamentUpdated($trackerLinkedTournament, 'tracker_deleted'))->toOthers();
            }
        }

        return redirect()->route('games.index')
            ->withMessage('Game removed from your list.');
    }

    public function observe(Game $game): Response|ResponseFactory
    {
        if (! $game->is_observable) {
            abort(404);
        }

        $game->load($this->eagerLoadsFor($game, GameShowContext::Observer));
        $this->ensureCrewReferences($game);

        return inertia('Games/Show', $this->buildShowProps($game, GameShowContext::Observer))
            ->withViewData(['page_meta' => $this->buildGameMeta($game)]);
    }

    /**
     * Shared social-meta builder for `observe` and `summary` — both are
     * publicly shareable game views, so unfurlers should see the players,
     * scenario, and outcome rather than the generic site default.
     */
    private function buildGameMeta(Game $game): array
    {
        // PHPDoc says GamePlayer->user is non-nullable, but solo games allow a
        // null user on slot 2 — fall back to opponent_name or the slot index.
        $players = $game->players->map(function ($p) {
            $name = $p->user->name ?? $p->opponent_name ?? 'Player '.$p->slot;
            $faction = $p->faction?->label();

            return $faction ? "$name ($faction)" : $name;
        })->implode(' vs ');

        $scenario = $game->strategy?->name;
        $status = match ($game->status) {
            GameStatusEnum::Completed => 'Completed',
            GameStatusEnum::Abandoned => 'Abandoned',
            GameStatusEnum::InProgress => 'In progress',
            default => 'Setup',
        };

        $description = sprintf(
            '%s · %s%s',
            $status,
            $game->encounter_size.'ss',
            $scenario ? ' · '.$scenario : '',
        );

        return $this->pageMeta(
            title: $players !== '' ? $players : ($game->name ?? 'Game'),
            description: $description,
        );
    }

    public function summary(Game $game): Response|ResponseFactory
    {
        if (! in_array($game->status, [GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            abort(404);
        }

        $game->load($this->eagerLoadsFor($game, GameShowContext::Summary));
        $this->ensureCrewReferences($game);

        return inertia('Games/Show', $this->buildShowProps($game, GameShowContext::Summary))
            ->withViewData(['page_meta' => $this->buildGameMeta($game)]);
    }

    public function toggleObservable(Game $game)
    {
        $this->authorize('update', $game);

        $game->update(['is_observable' => ! $game->is_observable]);

        return back();
    }

    public function updateSettings(UpdateGameSettingsRequest $request, Game $game)
    {
        $validated = $request->validated();

        $game->update(['settings' => array_merge($game->settings ?? [], $validated['settings'])]);

        return back();
    }

    private function getStartingCrews(Game $game): array
    {
        if (! in_array($game->status, [GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            return [];
        }

        $startingCrews = [];
        /** @var GamePlayer $player */
        foreach ($game->players as $player) {
            $startingCrews[$player->slot] = $player->crewMembers
                ->where('is_summoned', false)
                ->sortBy('sort_order')
                ->map(fn (GameCrewMember $m) => [
                    'display_name' => $m->display_name,
                    'faction' => $m->getRawOriginal('faction'),
                    'cost' => $m->cost,
                    'hiring_category' => $m->hiring_category,
                    'front_image' => $m->front_image,
                    'back_image' => $m->back_image,
                ])
                ->values()
                ->toArray();
        }

        return $startingCrews;
    }

    /**
     * Build each player's tracker references and attach them to the player.
     * The references are the union of: the crew build's stored references, the
     * player's LIVE crew members (so models summoned/added mid-game bring their
     * tokens — dynamic, never removed), plus the game's general tokens and the
     * Strategy's tokens (which apply to every crew).
     */
    private function ensureCrewReferences(Game $game): void
    {
        if (! in_array($game->status, [GameStatusEnum::InProgress, GameStatusEnum::Completed, GameStatusEnum::Abandoned])) {
            return;
        }

        // General tokens (Focus/Shielded/Impact…) + the game's Strategy tokens
        // (e.g. Plant Explosives → Explosive) belong in every crew's references.
        // Guard on the nullable FK (Bonanza has no Strategy) so we never touch
        // the eager-loaded relation when it's absent.
        $strategyTokens = $game->strategy_id ? $game->strategy->tokens : collect();
        $extraTokens = Token::general()->get()
            ->merge($strategyTokens)
            ->unique('id')
            ->map(fn (Token $t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug, 'description' => $t->description])
            ->values()
            ->all();

        foreach ($game->players as $player) {
            /** @var GamePlayer $player */
            $player->crewBuild?->ensureReferences();
            $player->setAttribute('references', $this->buildPlayerReferences($game, $player, $extraTokens));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $extraTokens
     * @return array<string, mixed>
     */
    private function buildPlayerReferences(Game $game, GamePlayer $player, array $extraTokens): array
    {
        $base = is_array($player->crewBuild?->references)
            ? $player->crewBuild->references
            : ['markers' => [], 'tokens' => [], 'upgrades' => [], 'characters' => []];

        // Live crew members cover models summoned/added during play.
        $characterIds = $player->crewMembers->pluck('character_id')->filter()->unique()->values()->all();
        $live = empty($characterIds) ? [] : CrewBuild::computeReferences($characterIds);

        $merged = $this->mergeReferences($base, $live);

        // Campaign games have no CrewBuild at all (crew comes from the Leader
        // Builder + Starting Arsenal instead), so the crew's Crew Card
        // Tier-4 borrows — real, keyword-matched Upgrade cards a Crew Card
        // Advancement grants the whole crew — never appeared here despite
        // being exactly the kind of thing a player looks up mid-game.
        // Only Upgrade-sourced borrows fit this tab's card-image shape; the
        // generic pg 15-16 CampaignCrewCard catalog has no card art and is
        // already visible via the Arsenal Sheet instead.
        $merged['upgrades'] = collect($merged['upgrades'])
            ->merge($this->campaignCrewCardReferenceUpgrades($game, $player))
            ->unique('id')->values()->all();

        $merged['tokens'] = collect($merged['tokens'])->merge($extraTokens)->unique('id')->sortBy('name')->values()->all();

        return $merged;
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string, front_image: string|null, back_image: string|null, type: string|null}>
     */
    private function campaignCrewCardReferenceUpgrades(Game $game, GamePlayer $player): array
    {
        if ($game->format !== \App\Enums\GameFormatEnum::Campaign || ! $player->user_id) {
            return [];
        }

        $crew = $this->resolveCampaignCrewForUser($game, $player->user_id);
        if (! $crew) {
            return [];
        }

        $crew->loadMissing(['crewCardEffect', 'crewCardAdvancements.crewCardEffect']);

        $upgrades = collect();
        if ($crew->crewCardEffect instanceof \App\Models\Upgrade) {
            $upgrades->push($crew->crewCardEffect);
        }
        foreach ($crew->crewCardAdvancements as $advancement) {
            if ($advancement->crewCardEffect instanceof \App\Models\Upgrade) {
                $upgrades->push($advancement->crewCardEffect);
            }
        }

        return $upgrades->unique('id')
            ->map(fn (\App\Models\Upgrade $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'slug' => $u->slug,
                'front_image' => $u->front_image,
                'back_image' => $u->back_image,
                'type' => $u->type?->label(),
            ])
            ->values()->all();
    }

    /**
     * Union two reference payloads by id — never removes (per QA: "don't worry about removing").
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private function mergeReferences(array $a, array $b): array
    {
        $out = ['version' => $a['version'] ?? CrewBuild::REFERENCES_VERSION];
        foreach (['markers', 'tokens', 'upgrades', 'characters'] as $key) {
            $out[$key] = collect($a[$key] ?? [])->merge($b[$key] ?? [])->unique('id')->values()->all();
        }

        return $out;
    }

    public function abandon(Game $game)
    {
        $this->authorize('view', $game);

        if ($game->status->isFinished()) {
            return redirect()->route('games.show', $game->uuid);
        }

        $game->update([
            'status' => GameStatusEnum::Abandoned,
            'completed_at' => now(),
        ]);

        if (! $game->is_solo) {
            broadcast(new GameStatusChanged($game))->toOthers();
        }

        // Surface abandon to a linked tournament — the TO's badge flips to
        // "abandoned" in real time so they can intervene (forfeit, re-enter score).
        $tg = \App\Models\TournamentGame::with('round.tournament')->where('game_id', $game->id)->first();
        if ($tg && $tg->round && $tg->round->tournament) {
            broadcast(new TournamentUpdated($tg->round->tournament, 'tracker_abandoned'))->toOthers();
        }

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('games.index')
            ->withMessage('Game abandoned.');
    }

    /**
     * Master characters (plus alternate leaders granted by crew upgrades)
     * grouped by name with their titles, for the MasterSelect UI.
     *
     * Bonanza Brawl branch: returns ALL standard non-hidden characters of any
     * station within the 11ss budget — the format hires a single non-Leader
     * model from anywhere in the faction (totems/peons priced via
     * Character::bonanzaCost). The same payload shape is reused so the
     * existing MasterSelect picker UI works without a refactor.
     */
    private function buildMastersProp(Game $game): array
    {
        $masterStatuses = [GameStatusEnum::MasterSelect, GameStatusEnum::CrewSelect];
        // Solo mode needs masters during faction_select too (for opponent setup after own faction).
        if ($game->is_solo) {
            $masterStatuses[] = GameStatusEnum::FactionSelect;
        }
        if (! in_array($game->status, $masterStatuses)) {
            return [];
        }

        if ($game->format === \App\Enums\GameFormatEnum::BonanzaBrawl) {
            return $this->buildBonanzaCharactersProp();
        }

        // Campaign games: the user's own pick comes from campaign_leader_option (a
        // separate prop). Solo Campaign games never show an opponent-master picker
        // either — the opponent is a generic placeholder set automatically in
        // GameSetupController::submitMaster() — so the catalog is never needed.
        if ($game->format === \App\Enums\GameFormatEnum::Campaign) {
            return [];
        }

        return $this->buildCatalogMasters();
    }

    /**
     * The current player's campaign leader shaped as a master-select option, so
     * campaign_leader_option can be surfaced to the Vue picker separately from
     * the catalog masters prop. Returns null before the leader has been built.
     *
     * PvP campaign games have a CampaignGame row linked via base_game_id — look
     * up the crew through that. Solo campaign games created via the standard game
     * tracker (no CampaignGame row) fall back to matching the user's crew by the
     * faction they selected in this game.
     *
     * @return array<string, mixed>|null
     */
    private function campaignLeaderMasterOption(Game $game): ?array
    {
        $campaignGame = \App\Models\Campaign\CampaignGame::query()
            ->where('base_game_id', $game->id)
            ->first();

        if ($campaignGame) {
            $crew = \App\Models\Campaign\CampaignCrew::query()
                ->where('campaign_id', $campaignGame->campaign_id)
                ->where('user_id', Auth::id())
                ->with('leader')
                ->first();
        } else {
            // Solo game created outside the campaign hub — no CampaignGame link.
            // Match the user's crew by the faction they picked for this game.
            if (! $game->is_solo) {
                return null;
            }
            $player = $game->players->first(fn ($p) => $p->user_id === Auth::id());
            if (! $player?->faction) {
                return null;
            }
            $crew = \App\Models\Campaign\CampaignCrew::query()
                ->where('user_id', Auth::id())
                ->where('faction', $player->getRawOriginal('faction'))
                ->whereHas('leader')
                ->with('leader')
                ->latest('id')
                ->first();
        }

        $leader = $crew?->leader;
        if (! $leader) {
            return null;
        }

        return [
            'name' => $leader->name,
            'faction' => $leader->getRawOriginal('faction'),
            'second_faction' => $leader->getRawOriginal('second_faction'),
            'front_image' => $leader->getRawOriginal('front_image'),
            'is_alternate_leader' => false,
            'titles' => [[
                'id' => $leader->id,
                'display_name' => $leader->name,
                'title' => null,
            ]],
        ];
    }

    /**
     * Full catalog of masters + alternate leaders, grouped by name, for the
     * standard master-select picker. Shared by non-campaign games and the
     * opponent-master pick in solo campaign games.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCatalogMasters(): array
    {
        $characters = Character::standard()->where('station', 'master')
            ->where('is_hidden', false)
            ->with('miniatures')
            ->orderBy('name')
            ->orderBy('title')
            ->get();

        $alternateLeaderIds = \App\Models\Upgrade::standard()->forCrews()
            ->whereNotNull('hiring_rules')
            ->pluck('hiring_rules')
            ->map(fn ($rules) => $rules['alternate_leader_id'] ?? null)
            ->filter()
            ->unique();

        if ($alternateLeaderIds->isNotEmpty()) {
            $altLeaders = Character::standard()->whereIn('id', $alternateLeaderIds)
                ->where('is_hidden', false)
                ->with('miniatures')
                ->get();

            foreach ($altLeaders as $alt) {
                if (! $characters->contains('id', $alt->id)) {
                    $characters->push($alt);
                }
            }
        }

        $characters = $characters->sortBy('name')->sortBy('title');

        $grouped = [];
        foreach ($characters->groupBy('name') as $name => $group) {
            /** @var Character $first */
            $first = $group->first();
            $grouped[] = [
                'name' => $name,
                'faction' => $first->getRawOriginal('faction'),
                'second_faction' => $first->getRawOriginal('second_faction'),
                'front_image' => $first->miniatures->first()?->front_image,
                'is_alternate_leader' => $alternateLeaderIds->contains($first->id),
                'titles' => $group->map(fn (Character $c) => [
                    'id' => $c->id,
                    'display_name' => $c->display_name,
                    'title' => $c->title,
                ])->values(),
            ];
        }

        return $grouped;
    }

    /**
     * Faction-wide character pool for Bonanza Brawl picker. Filters to anyone
     * (any station) hireable within 11ss using the format's totem/peon cost
     * derivation. Output mirrors the MasterSelect grouped shape so the same
     * picker UI renders.
     */
    private function buildBonanzaCharactersProp(): array
    {
        $characters = Character::standard()
            ->where('is_hidden', false)
            // Bonanza hires a single non-Leader model — Masters are never
            // fielded, so they're excluded from the model-select even when
            // their derived bonanzaCost() lands within the budget. Keep
            // NULL-station models (totems, peons, etc.): a bare
            // `station != 'master'` would drop them since `NULL != 'master'`
            // is not true in SQL.
            ->where(fn ($q) => $q->whereNull('station')->orWhere('station', '!=', 'master'))
            ->with('miniatures')
            ->orderBy('name')
            ->orderBy('title')
            ->get()
            ->filter(fn (Character $c) => $c->bonanzaCost() <= \App\Enums\GameFormatEnum::BonanzaBrawl->defaultEncounterSize())
            ->values();

        $grouped = [];
        foreach ($characters->groupBy('name') as $name => $group) {
            /** @var Character $first */
            $first = $group->first();
            $grouped[] = [
                'name' => $name,
                'faction' => $first->getRawOriginal('faction'),
                'second_faction' => $first->getRawOriginal('second_faction'),
                'front_image' => $first->miniatures->first()?->front_image,
                'is_alternate_leader' => false,
                // Each title is a separate hireable in Bonanza; tag with the
                // effective cost so the UI can show "(7ss)" hints inline.
                'titles' => $group->map(fn (Character $c) => [
                    'id' => $c->id,
                    'display_name' => $c->display_name,
                    'title' => $c->title,
                    'bonanza_cost' => $c->bonanzaCost(),
                ])->values(),
            ];
        }

        return $grouped;
    }

    /**
     * Current user's saved CrewBuilds shaped for the CrewSelect list — each
     * preview carries a summary of members and computed soulstone pool so the
     * UI can render without drilling into relations.
     */
    private function buildMyCrewsProp(Game $game): array
    {
        if (! in_array($game->status, [GameStatusEnum::MasterSelect, GameStatusEnum::CrewSelect]) || ! Auth::check()) {
            return [];
        }

        $builds = CrewBuild::where('user_id', Auth::id())
            ->where('is_archived', false)
            ->with('master.keywords')
            ->orderBy('updated_at', 'desc')
            ->get();

        // One big character load — avoids N+1 per-build when shaping members below.
        $totemIds = $builds->map(fn (CrewBuild $b) => $b->master?->has_totem_id)->filter()->unique(); // @phpstan-ignore nullsafe.neverNull
        $allCharIds = $builds->flatMap(fn (CrewBuild $b) => $b->crew_data ?? [])->unique()->merge($totemIds)->unique();
        $characters = Character::with('keywords', 'characteristics')
            ->whereIn('id', $allCharIds)->get()->keyBy('id');

        $result = [];
        foreach ($builds as $b) {
            $master = $b->master;
            if (! $master) {
                continue;
            }

            $leaderKeywords = $master->keywords->pluck('slug')->toArray();
            $members = [];
            $totalSpent = 0;
            $ookCount = 0;

            // Leader(s) — masters with count > 1 (pair models) need one entry
            // per physical model so the preview matches the real game-tracker crew.
            $leaderCount = max(1, $master->count ?? 1);
            for ($i = 0; $i < $leaderCount; $i++) {
                $members[] = [
                    'display_name' => $master->display_name,
                    'faction' => $master->getRawOriginal('faction'),
                    'cost' => 0,
                    'effective_cost' => 0,
                    'category' => 'leader',
                ];
            }

            if ($master->has_totem_id) {
                $totem = $characters->get($master->has_totem_id) ?? Character::find($master->has_totem_id);
                if ($totem) {
                    $totemCount = max(1, $totem->count ?? 1);
                    for ($i = 0; $i < $totemCount; $i++) {
                        $members[] = [
                            'display_name' => $totem->display_name,
                            'faction' => $totem->getRawOriginal('faction'),
                            'cost' => 0,
                            'effective_cost' => 0,
                            'category' => 'totem',
                        ];
                    }
                }
            }

            foreach ($b->crew_data ?? [] as $id) {
                $char = $characters->get($id);
                if (! $char) {
                    continue;
                }

                $sharesKeyword = $char->keywords->pluck('slug')->intersect($leaderKeywords)->isNotEmpty();
                $isVersatile = $char->characteristics->pluck('name')->map(fn ($n) => strtolower($n))->contains('versatile');
                $category = $sharesKeyword ? 'in-keyword' : ($isVersatile ? 'versatile' : 'ook');
                $effectiveCost = $category === 'ook' ? ($char->cost + 1) : $char->cost;
                $totalSpent += $effectiveCost;
                if ($category === 'ook') {
                    $ookCount++;
                }

                $members[] = [
                    'display_name' => $char->display_name,
                    'faction' => $char->getRawOriginal('faction'),
                    'cost' => $char->cost,
                    'effective_cost' => $effectiveCost,
                    'category' => $category,
                ];
            }

            $remaining = $b->encounter_size - $totalSpent;
            $result[] = [
                'id' => $b->id,
                'name' => $b->name,
                'share_code' => $b->share_code,
                'faction' => $b->getRawOriginal('faction'),
                'master_name' => $master->display_name,
                'encounter_size' => $b->encounter_size,
                'crew_count' => count($b->crew_data ?? []) + 1,
                'total_spent' => $totalSpent,
                'soulstone_pool' => $remaining > 6 ? 6 : max(0, $remaining),
                'ook_count' => $ookCount,
                'is_over_budget' => $totalSpent > $game->encounter_size,
                'members' => $members,
            ];
        }

        return $result;
    }

    /**
     * What the current user can infer about the opponent's scheme state:
     * the last revealed scheme (scored/discarded), a possible-schemes pool
     * derived from follow-ups of that reveal, and a turn-by-turn history
     * with held turns attributed retroactively once the scheme reveals.
     */
    private function buildOpponentSchemeIntel(Game $game, \Illuminate\Support\Collection $schemeCache): ?array
    {
        if ($game->status !== GameStatusEnum::InProgress) {
            return null;
        }

        $userId = Auth::id();
        /** @var GamePlayer|null $opponent */
        $opponent = $game->is_solo
            ? $game->players->firstWhere('slot', 2)
            : $game->players->first(fn ($p) => $p->user_id !== $userId);

        if (! $opponent) {
            return null;
        }

        // Possible schemes come from the opponent's LAST REVEALED scheme's
        // follow-ups — NOT their stored scheme_pool, which reflects an
        // as-yet-hidden next-scheme pick. Before any reveal: the shared game pool.
        $lastRevealedTurn = $opponent->turns
            ->sortByDesc('turn_number')
            ->first(fn (GameTurn $t) => in_array($t->scheme_action, ['scored', 'discarded']));

        if ($lastRevealedTurn && $lastRevealedTurn->scheme_id) {
            $revealedScheme = $schemeCache->get($lastRevealedTurn->scheme_id);
            $poolIds = $revealedScheme
                ? array_values(array_filter([
                    $revealedScheme->next_scheme_one_id,
                    $revealedScheme->next_scheme_two_id,
                    $revealedScheme->next_scheme_three_id,
                ]))
                : ($game->scheme_pool ?? []);
            if (empty($poolIds)) {
                $poolIds = $game->scheme_pool ?? [];
            }
        } else {
            $poolIds = $game->scheme_pool ?? [];
        }

        $possible = self::schemesFromCache($schemeCache, $poolIds);

        // Scheme history: walk turns in order, buffering held turns. When a
        // scored/discarded turn arrives it retroactively reveals the scheme
        // the buffered held turns belonged to. Trailing held turns stay hidden.
        $schemeHistory = [];
        $heldBuffer = [];
        foreach ($opponent->turns->sortBy('turn_number') as $t) {
            /** @var GameTurn $t */
            if ($t->scheme_action === null) {
                continue;
            }

            if ($t->scheme_action === 'held') {
                $heldBuffer[] = $t->turn_number;

                continue;
            }

            $scheme = $t->scheme_id ? $schemeCache->get($t->scheme_id) : null;
            $schemeName = $scheme->name ?? 'Unknown';
            foreach ($heldBuffer as $heldTurn) {
                $schemeHistory[] = [
                    'turn_number' => $heldTurn,
                    'scheme_id' => $t->scheme_id,
                    'scheme_name' => $schemeName,
                    'scheme_action' => 'held',
                ];
            }
            $heldBuffer = [];

            $schemeHistory[] = [
                'turn_number' => $t->turn_number,
                'scheme_id' => $t->scheme_id,
                'scheme_name' => $schemeName,
                'scheme_action' => $t->scheme_action,
            ];
        }

        $lastRevealed = collect($schemeHistory)
            ->last(fn ($h) => in_array($h['scheme_action'], ['scored', 'discarded']));

        return [
            'last_revealed' => $lastRevealed,
            'possible_schemes' => $possible,
            'scheme_history' => $schemeHistory,
        ];
    }

    /**
     * Schemes scored by each player this game plus any currently-held scheme.
     * Completed games include every scheme referenced across all turns, even
     * ones that left the pool via follow-up chains.
     */
    private function buildCurrentSchemesProp(Game $game): array
    {
        if ($game->status === GameStatusEnum::Completed) {
            $turnSchemeIds = GameTurn::where('game_id', $game->id)
                ->whereNotNull('scheme_id')
                ->pluck('scheme_id')
                ->unique()
                ->values();
            $playerSchemeIds = $game->players->pluck('current_scheme_id')->filter();
            $allIds = $turnSchemeIds->merge($playerSchemeIds)->unique()->values();

            return Scheme::whereIn('id', $allIds)->get()->map(fn (Scheme $s) => $s->toTrackerArray())->toArray();
        }

        if ($game->status !== GameStatusEnum::InProgress) {
            return [];
        }

        $schemeIds = $game->players->pluck('current_scheme_id')->filter()->unique()->values();

        return Scheme::whereIn('id', $schemeIds)->get()->map(fn (Scheme $s) => $s->toTrackerArray())->toArray();
    }

    /** Filter a keyed scheme cache by id list and shape each for the tracker. */
    private static function schemesFromCache(\Illuminate\Support\Collection $schemeCache, array $ids): array
    {
        return $schemeCache->filter(fn (Scheme $s) => in_array($s->id, $ids))
            ->map(fn (Scheme $s) => $s->toTrackerArray())
            ->values()
            ->toArray();
    }

    /**
     * Per-player possible-schemes hints for public observers: each slot gets
     * the follow-ups of their last revealed scheme, plus whether they scored
     * (revealing the held scheme) on the current turn.
     */
    private function buildObserverSchemeIntel(Game $game): ?array
    {
        if ($game->status !== GameStatusEnum::InProgress) {
            return null;
        }

        $schemeCache = Scheme::forSeason($game->season)->get()->keyBy('id');
        $result = [];

        foreach ($game->players as $player) {
            $lastRevealedTurn = $player->turns
                ->sortByDesc('turn_number')
                ->first(fn ($t) => in_array($t->scheme_action, ['scored', 'discarded']));

            if ($lastRevealedTurn && $lastRevealedTurn->scheme_id) {
                $revealedScheme = $schemeCache->get($lastRevealedTurn->scheme_id);
                $possibleIds = $revealedScheme ? array_values(array_filter([
                    $revealedScheme->next_scheme_one_id,
                    $revealedScheme->next_scheme_two_id,
                    $revealedScheme->next_scheme_three_id,
                ])) : [];
            } else {
                $possibleIds = $game->scheme_pool ?? [];
            }

            $currentTurnRecord = $player->turns->firstWhere('turn_number', $game->current_turn);
            $revealedThisTurn = $currentTurnRecord && $currentTurnRecord->scheme_action === 'scored';

            $result[$player->slot] = [
                'possible_schemes' => self::schemesFromCache($schemeCache, $possibleIds),
                'revealed_scheme_id' => $revealedThisTurn ? $currentTurnRecord->scheme_id : null,
                'last_scored_turn' => $revealedThisTurn ? $currentTurnRecord->turn_number : null,
            ];
        }

        return $result;
    }
}
