<?php

namespace App\Models\Campaign;

use App\Enums\Campaign\CampaignPlayerRoleEnum;
use App\Models\User;
use Database\Factories\Campaign\CampaignPlayerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot for campaign membership. Distinct from `campaign_crews` (which is
 * one-per-player and carries arsenal data) so we can record co-organizers
 * who don't run a crew, and so role transitions don't touch crew rows.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $user_id
 * @property CampaignPlayerRoleEnum $role
 * @mixin IdeHelperCampaignPlayer
 */
class CampaignPlayer extends Model
{
    /** @use HasFactory<CampaignPlayerFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'role' => CampaignPlayerRoleEnum::class,
        ];
    }

    protected static function newFactory(): CampaignPlayerFactory
    {
        return CampaignPlayerFactory::new();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
