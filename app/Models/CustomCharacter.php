<?php

namespace App\Models;

use App\Enums\BaseSizeEnum;
use App\Enums\Campaign\LeaderTagEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Jobs\Campaign\GenerateLeaderCardImage;
use App\Models\Campaign\CampaignArsenalModelInjury;
use App\Models\Concerns\HasCardVersions;
use App\Observers\CustomCharacterObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @property bool|null $campaign_still_live Virtual attribute, not a DB column — set by
 *                                          CustomCharacterController::index() only, batched per-request against the
 *                                          linked CampaignCrew's campaign status. Absent (null) anywhere else.
 *
 * @mixin IdeHelperCustomCharacter
 */
#[ObservedBy(CustomCharacterObserver::class)]
class CustomCharacter extends Model
{
    use HasCardVersions;
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * Queue a render of the saved card image (front/back/combination PNGs). Campaign Leaders/Totems render
     * immediately — the Arsenal Sheet polls for the result. Every other card is rendered shortly after the last save:
     * while a render is already waiting the extra call is dropped, because that job reads the card as it is when it
     * runs, so a burst of edits costs one headless-Chrome render rather than one per save.
     */
    public function queueCardImage(): void
    {
        if ($this->is_campaign_leader || $this->is_campaign_totem) {
            GenerateLeaderCardImage::dispatch($this->id)->afterCommit();

            return;
        }

        if (! Cache::add(self::renderPendingKey($this->id), true, 60)) {
            return;
        }

        GenerateLeaderCardImage::dispatch($this->id)->delay(now()->addSeconds(20))->afterCommit();
    }

    /** Cache key marking a delayed render as waiting; the job clears it the moment it starts reading the card. */
    /** True while a render is waiting for its delay or running — drives the "Rendering…" state on My Cards. */
    public function cardImagePending(): bool
    {
        return Cache::has(self::renderPendingKey($this->id)) || Cache::has(self::renderingKey($this->id));
    }

    /** Set by the job while headless Chrome is working, so the pending flag survives the marker being released at start. */
    public static function renderingKey(int $id): string
    {
        return "custom-card-rendering:{$id}";
    }

    public static function renderPendingKey(int $id): string
    {
        return "custom-card-render-pending:{$id}";
    }

    /** @return BelongsTo<CardArt, $this> */
    public function cardArt(): BelongsTo
    {
        return $this->belongsTo(CardArt::class, 'card_art_id');
    }

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
            'second_faction' => FactionEnum::class,
            'station' => CharacterStationEnum::class,
            'base' => BaseSizeEnum::class,
            'defense_suit' => SuitEnum::class,
            'willpower_suit' => SuitEnum::class,
            'generates_stone' => 'boolean',
            'is_unhirable' => 'boolean',
            'is_public' => 'boolean',
            'actions' => 'array',
            'abilities' => 'array',
            'keywords' => 'array',
            'characteristics' => 'array',
            'linked_crew_upgrades' => 'array',
            'linked_totems' => 'array',
            // Campaign-mode extension flags. Booleans cast via stringly-typed
            // SQLite columns; without these the `current` check returns 0/1
            // ints and trips equality comparisons.
            'is_campaign_leader' => 'boolean',
            'is_campaign_totem' => 'boolean',
            'is_campaign_black_joker_totem' => 'boolean',
            'is_campaign_red_joker_totem' => 'boolean',
            'miraculous_recovery_used' => 'boolean',
            'current' => 'boolean',
            'annihilated_at' => 'datetime',
            'replaced_at' => 'datetime',
            'card_image_generated_at' => 'datetime',
            'xp_track' => 'array',
            'card_art_x' => 'integer',
            'card_art_y' => 'integer',
            'card_art_zoom' => 'integer',
            'card_art_dx' => 'integer',
            'card_art_dy' => 'integer',
            // Integer casts on campaign-mode stat columns. SQLite returns
            // strings for numeric columns by default, which trips comparison
            // operators (e.g. campaign_totem_flip_value === $flipped). Cast
            // them explicitly so reads return PHP ints.
            'campaign_health' => 'integer',
            'campaign_df' => 'integer',
            'campaign_wp' => 'integer',
            'campaign_sp' => 'integer',
            'campaign_size' => 'integer',
            'campaign_br' => 'integer',
        ];
    }

    /**
     * The `characteristics` column plus the Bruiser/Strategist tag (pg 18)
     * and the "Unique" characteristic (every built Leader is a one-of-a-kind
     * named model), for display only. Not persisted onto `characteristics`
     * itself so it doesn't round-trip into the Leader Builder edit form and
     * get counted against its two-pick cap or duplicated on repeat saves.
     * Every render surface (live Arsenal Sheet, card image capture, print
     * sheet) should call this instead of reading `characteristics` raw.
     *
     * @return array<int, string>
     */
    public function displayCharacteristics(): array
    {
        $characteristics = $this->characteristics ?? [];

        if (! $this->is_campaign_leader) {
            return $characteristics;
        }

        if (! in_array('Unique', $characteristics, true)) {
            $characteristics[] = 'Unique';
        }
        if ($tag = LeaderTagEnum::tryFrom((string) $this->tag)) {
            $characteristics[] = $tag->label();
        }

        return $characteristics;
    }

    /**
     * Canonical 39-box Leadership Experience chart (rulebook pg 31, 13 + 13 + 13).
     * Tier numbers per the printed track; a null tier means the XP point is
     * earned without triggering an advancement at that position. Used to lazy-
     * initialize a leader's `xp_track` column on first read.
     *
     * @return array<int, array{index: int, filled: bool, tier: int|null}>
     */
    public static function defaultXpTrack(): array
    {
        $row1 = [1, 1, 2, null, 3, null, 4, null, 1, null, 2, null, 4];
        $row2 = [null, null, null, 1, null, null, 2, 1, null, null, null, 3, null];
        $row3 = [null, null, 1, null, null, null, null, 2, null, null, null, null, 4];

        $track = [];
        $index = 0;
        foreach (array_merge($row1, $row2, $row3) as $tier) {
            $track[] = ['index' => $index, 'filled' => false, 'tier' => $tier];
            $index++;
        }

        return $track;
    }

    protected $appends = [
        'faction_color',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomCharacter $character) {
            if (! $character->share_code) {
                $character->share_code = Str::random(12);
            }
        });
    }

    public function getFactionColorAttribute(): string
    {
        // faction is nullable (see 2026_06_17_160100) — falls back to neutral
        // rather than erroring for any row saved without one.
        return $this->faction?->color() ?? 'neutral';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Injuries attached to a Leader/Totem via the Back-Alley Doctor (pg 33) —
     * shares campaign_arsenal_model_injuries with CampaignArsenalModel, keyed
     * by custom_character_id instead.
     *
     * @return HasMany<CampaignArsenalModelInjury, $this>
     */
    public function injuries(): HasMany
    {
        return $this->hasMany(CampaignArsenalModelInjury::class, 'custom_character_id');
    }

    /**
     * Attempts to annihilate this leader/totem, respecting Miraculous Recovery
     * (pg 24): the first time a leader/totem would be annihilated, it survives
     * instead and the reprieve is spent. Returns true if it survived (Fate
     * spent), false if it actually annihilated. Caller is responsible for
     * locking + rechecking state before calling this inside a transaction.
     */
    public function attemptAnnihilation(): bool
    {
        if (! $this->miraculous_recovery_used) {
            $this->update(['miraculous_recovery_used' => true]);

            return true;
        }

        $this->update(['current' => false, 'annihilated_at' => now()]);

        return false;
    }

    /** @return array<int, string> */
    public static function versionedColumns(): array
    {
        return [
            'name', 'title', 'faction', 'second_faction', 'station', 'cost', 'health', 'size', 'base', 'defense', 'defense_suit',
            'willpower', 'willpower_suit', 'speed', 'count', 'summon_target_number', 'generates_stone', 'is_unhirable',
            'card_art_id', 'card_art_x', 'card_art_y', 'card_art_zoom', 'card_art_dx', 'card_art_dy', 'actions', 'abilities',
            'keywords', 'characteristics', 'linked_crew_upgrades', 'linked_totems',
        ];
    }

    public function isCampaignManaged(): bool
    {
        return (bool) ($this->is_campaign_leader || $this->is_campaign_totem);
    }
}
