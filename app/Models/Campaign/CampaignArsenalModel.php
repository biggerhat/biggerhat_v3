<?php

namespace App\Models\Campaign;

use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use Database\Factories\Campaign\CampaignArsenalModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
