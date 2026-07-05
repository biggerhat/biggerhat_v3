<?php

namespace App\Models\Campaign;

use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Upgrade;
use Database\Factories\Campaign\CampaignArsenalModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One non-leader model owned by a campaign crew's arsenal. Annihilated
 * models stay around for history (annihilated_at != null) and are excluded
 * from active hiring/scoring queries via the `active()` scope.
 *
 * @property int $id
 * @property int $campaign_crew_id
 * @property int $character_id
 * @property int|null $miniature_id
 * @property string|null $label
 * @property bool $is_peon
 * @property string|null $title_group_key
 * @property int|null $acquired_week
 * @property string $acquired_via
 * @property int|null $granted_keyword_id
 * @property \Carbon\CarbonImmutable|null $annihilated_at
 * @property \Carbon\CarbonImmutable|null $removed_at
 *
 * @mixin IdeHelperCampaignArsenalModel
 */
class CampaignArsenalModel extends Model
{
    /** @use HasFactory<CampaignArsenalModelFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'is_peon' => 'boolean',
            'ignored_for_limits' => 'boolean',
            'gained_characteristics' => 'array',
            'gained_lucky_miss_ids' => 'array',
            'annihilated_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): CampaignArsenalModelFactory
    {
        return CampaignArsenalModelFactory::new();
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(CampaignCrew::class, 'campaign_crew_id');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function miniature(): BelongsTo
    {
        return $this->belongsTo(Miniature::class);
    }

    public function grantedKeyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'granted_keyword_id');
    }

    /** Rows that count toward the crew's current ss + scoring footprint. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('annihilated_at')->whereNull('removed_at');
    }

    /**
     * Pg 22 (Peons): "Peons may be added to your arsenal and hired as normal,
     * but they cannot have equipment attached, gain injuries, or be
     * annihilated." Use this guard wherever equipment attachment lands so the
     * rule is enforced server-side at the attach call-site.
     */
    public function canReceiveEquipment(): bool
    {
        return ! $this->is_peon && $this->annihilated_at === null && $this->removed_at === null;
    }

    /** @return HasMany<CampaignArsenalModelInjury, $this> */
    public function injuries(): HasMany
    {
        return $this->hasMany(CampaignArsenalModelInjury::class, 'campaign_arsenal_model_id');
    }

    /**
     * Attach one injury upgrade per the Determine Injuries rules (pg 34):
     *   - Peons and already-annihilated models never gain injuries.
     *   - A self-annihilating result (e.g. "Killed Off") removes the model.
     *   - "Just a Flesh Wound" entries attach nothing.
     *   - A duplicate of an injury the model already has is ignored ("the model
     *     got lucky").
     *   - A model holding 3+ injuries afterwards is annihilated.
     *   - Titled models share injuries: the same injury cascades to every
     *     sibling sharing this model's title_group_key (pg 18).
     *
     * The caller owns the surrounding lock/transaction; sibling rows are locked
     * here. Returns true when an injury upgrade was actually attached to THIS
     * model (false for peon/dup/flesh-wound/annihilate cases).
     */
    public function attachInjury(Upgrade $injury, ?int $aftermathId = null, bool $cascadeToTitles = true): bool
    {
        if ($this->is_peon || $this->annihilated_at !== null) {
            return false;
        }

        // Self-annihilating injury (Killed Off): the model is simply removed,
        // no upgrade row is attached and nothing cascades.
        if ($injury->campaign_annihilates_model) {
            $this->update(['annihilated_at' => now()]);

            return false;
        }

        // Purely-cosmetic "Just a Flesh Wound" rows attach nothing.
        if (str_contains(strtolower((string) $injury->name), 'flesh wound')) {
            return false;
        }

        // No duplicates (pg 34): a flipped injury the model already has is
        // ignored — but it may still cascade to titled siblings that lack it.
        // One read of the current injury ids covers both the dup-check and the
        // post-attach 3-injury annihilation count.
        $existingInjuryIds = $this->injuries()->pluck('injury_upgrade_id');
        $attached = false;
        if (! $existingInjuryIds->contains($injury->id)) {
            $this->injuries()->create([
                'injury_upgrade_id' => $injury->id,
                'acquired_aftermath_id' => $aftermathId,
            ]);
            $attached = true;

            if ($existingInjuryIds->count() + 1 >= 3) {
                $this->update(['annihilated_at' => now()]);
            }
        }

        if ($cascadeToTitles && $this->title_group_key !== null) {
            $siblings = self::query()
                ->where('campaign_crew_id', $this->campaign_crew_id)
                ->where('title_group_key', $this->title_group_key)
                ->where('id', '!=', $this->id)
                ->whereNull('annihilated_at')
                ->lockForUpdate()
                ->get();

            foreach ($siblings as $sibling) {
                $sibling->attachInjury($injury, $aftermathId, cascadeToTitles: false);
            }
        }

        return $attached;
    }

    /**
     * Record a Lucky Miss upgrade the model gained from a red-joker injury /
     * doctor result (pg 36). Stored as a list of lucky_miss_catalog ids; all
     * Lucky Miss results are beneficial and never affect Campaign Rating.
     * Duplicates are ignored. (The "Doppelganger" any-joker result is handled
     * separately — it creates a copied arsenal model, not an upgrade here.)
     */
    public function applyLuckyMiss(int $luckyMissId): void
    {
        $current = $this->gained_lucky_miss_ids ?? [];
        if (! in_array($luckyMissId, $current, true)) {
            $current[] = $luckyMissId;
            $this->update(['gained_lucky_miss_ids' => $current]);
        }
    }

    /**
     * Create a copy of this model in a crew's arsenal, carrying its injuries.
     * Used by Traitor (→ the opponent crew, pg 34) and Doppelganger (→ this
     * crew, pg 36). The copy is its own model — it leaves the title group, and
     * its gained Lucky Miss effects are not carried. Equipment is crew-level in
     * this data model and is not duplicated here.
     */
    public function copyForCampaign(int $targetCrewId, string $acquiredVia, bool $ignoredForLimits = false): self
    {
        $copy = self::create([
            'campaign_crew_id' => $targetCrewId,
            'character_id' => $this->character_id,
            'miniature_id' => $this->miniature_id,
            'label' => $this->label,
            'is_peon' => $this->is_peon,
            'ignored_for_limits' => $ignoredForLimits,
            'title_group_key' => null,
            'gained_characteristics' => $this->gained_characteristics,
            'granted_keyword_id' => $this->granted_keyword_id,
            'acquired_via' => $acquiredVia,
        ]);

        foreach ($this->injuries()->get() as $injury) {
            $copy->injuries()->create([
                'injury_upgrade_id' => $injury->injury_upgrade_id,
                'acquired_aftermath_id' => $injury->acquired_aftermath_id,
            ]);
        }

        return $copy;
    }
}
