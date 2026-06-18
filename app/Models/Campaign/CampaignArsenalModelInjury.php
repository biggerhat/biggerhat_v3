<?php

namespace App\Models\Campaign;

use App\Models\Upgrade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row: one injury upgrade attached to one arsenal model (pg 34). A model
 * may hold up to two injuries; the third (distinct) injury annihilates it. The
 * same injury is never stacked twice on one model — a duplicate flip is ignored
 * ("the model got lucky and suffers no injury this game").
 *
 * @property int $id
 * @property int $campaign_arsenal_model_id
 * @property int $injury_upgrade_id
 * @property int|null $acquired_aftermath_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property-read CampaignArsenalModel $arsenalModel
 * @property-read Upgrade|null $injury
 *
 * @mixin \Eloquent
 * @mixin IdeHelperCampaignArsenalModelInjury
 */
class CampaignArsenalModelInjury extends Model
{
    protected $table = 'campaign_arsenal_model_injuries';

    protected $guarded = ['id'];

    public function arsenalModel(): BelongsTo
    {
        return $this->belongsTo(CampaignArsenalModel::class, 'campaign_arsenal_model_id');
    }

    public function injury(): BelongsTo
    {
        return $this->belongsTo(Upgrade::class, 'injury_upgrade_id');
    }
}
