<?php

namespace App\Models\Campaign;

use App\Models\Game;
use Database\Factories\Campaign\CampaignGameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Campaign-context wrapper around a standard Game. Lives 1:1 with the base
 * `games` row (linked by `base_game_id`) and carries the campaign-only
 * fields: encounter math, CR snapshots taken at game-start time, ss-pool
 * bonus for the lower-rated crew, scoring fields populated during Aftermath.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $week_number
 * @property int $crew_a_id
 * @property int|null $crew_b_id
 * @property int|null $base_game_id
 * @property int $encounter_size
 * @property int $cr_a
 * @property int $cr_b
 * @property int $ss_bonus_to_lower
 * @property int|null $winner_crew_id
 * @property int|null $withdrew_crew_id
 * @property int|null $withdrew_turn
 * @property int $vp_a
 * @property int $vp_b
 * @property int $schemes_completed_a
 * @property int $schemes_completed_b
 * @property int|null $weekly_event_id
 * @property string $status
 * @property-read Campaign $campaign
 * @property-read CampaignCrew $crewA
 * @property-read CampaignCrew|null $crewB
 * @property-read \App\Models\Game|null $baseGame
 *
 * @mixin IdeHelperCampaignGame
 */
class CampaignGame extends Model
{
    /** @use HasFactory<CampaignGameFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): CampaignGameFactory
    {
        return CampaignGameFactory::new();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function crewA(): BelongsTo
    {
        return $this->belongsTo(CampaignCrew::class, 'crew_a_id');
    }

    public function crewB(): BelongsTo
    {
        return $this->belongsTo(CampaignCrew::class, 'crew_b_id');
    }

    public function baseGame(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'base_game_id');
    }
}
