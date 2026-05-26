<?php

namespace App\Models\Campaign;

use App\Enums\FactionEnum;
use App\Models\CustomCharacter;
use App\Models\Keyword;
use App\Models\User;
use Database\Factories\Campaign\CampaignCrewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * A player's arsenal entry inside a Campaign. Created automatically when the
 * user accepts an invitation. Initially stub — name + share_code only — and
 * the player completes it during Leader Build + Starting Arsenal flows
 * (Phases 4-5 of the plan).
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $user_id
 * @property string $name
 * @property string $share_code
 * @property FactionEnum|null $faction
 * @property int|null $keyword_1_id
 * @property int|null $keyword_2_id
 * @property int|null $crew_card_effect_id
 * @property int $scrip
 * @property int $total_wins
 * @property \Carbon\CarbonImmutable|null $retired_at
 * @property \Carbon\CarbonImmutable|null $starting_anew_at
 * @property-read Campaign $campaign
 * @property-read \App\Models\User|null $user
 * @property-read Keyword|null $keywordOne
 * @property-read Keyword|null $keywordTwo
 * @property-read CrewCardEffect|null $crewCardEffect
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignArsenalModel> $arsenalModels
 */
class CampaignCrew extends Model
{
    /** @use HasFactory<CampaignCrewFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
            'retired_at' => 'immutable_datetime',
            'starting_anew_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'share_code';
    }

    protected static function booted(): void
    {
        static::creating(function (CampaignCrew $crew) {
            if (! $crew->share_code) {
                $crew->share_code = Str::random(12);
            }
        });
    }

    protected static function newFactory(): CampaignCrewFactory
    {
        return CampaignCrewFactory::new();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function keywordOne(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'keyword_1_id');
    }

    public function keywordTwo(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'keyword_2_id');
    }

    public function crewCardEffect(): BelongsTo
    {
        return $this->belongsTo(CrewCardEffect::class);
    }

    public function arsenalModels(): HasMany
    {
        return $this->hasMany(CampaignArsenalModel::class);
    }

    /**
     * The crew's current Leader (CustomCharacter row flagged as leader+current).
     * Backed by the composite index on (campaign_crew_id, is_campaign_leader, current).
     */
    public function leader(): HasOne
    {
        return $this->hasOne(CustomCharacter::class, 'campaign_crew_id')
            ->where('is_campaign_leader', true)
            ->where('current', true);
    }

    /**
     * The crew's current Totem (unlocked at Tier-3). Same index pattern as leader.
     */
    public function totem(): HasOne
    {
        return $this->hasOne(CustomCharacter::class, 'campaign_crew_id')
            ->where('is_campaign_totem', true)
            ->where('current', true);
    }
}
