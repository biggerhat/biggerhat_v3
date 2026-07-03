<?php

namespace App\Models\Campaign;

use App\Enums\Campaign\CampaignStatusEnum;
use App\Models\User;
use Database\Factories\Campaign\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Malifaux 4E Campaign Mode game. One per group of players. The organizer
 * creates it and invites others; each invited user gets a `CampaignCrew` row
 * once they accept. Status transitions: planning → active → ended.
 *
 * @property int $id
 * @property string $name
 * @property int $length_weeks
 * @property int $current_week
 * @property int $organizer_user_id
 * @property CampaignStatusEnum $status
 * @property array<string, bool>|null $optional_rules
 * @property bool $competitive
 * @property bool $weekly_event_active
 * @property bool $is_solo
 * @property \Carbon\CarbonImmutable|null $started_at
 * @property \Carbon\CarbonImmutable|null $ended_at
 * @property-read \App\Models\User|null $organizer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignPlayer> $players
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignInvitation> $invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignCrew> $crews
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampaignWeek> $weeks
 *
 * @mixin IdeHelperCampaign
 */
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'status' => CampaignStatusEnum::class,
            'optional_rules' => 'array',
            'competitive' => 'boolean',
            'weekly_event_active' => 'boolean',
            'is_solo' => 'boolean',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): CampaignFactory
    {
        return CampaignFactory::new();
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_user_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(CampaignPlayer::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CampaignInvitation::class);
    }

    public function crews(): HasMany
    {
        return $this->hasMany(CampaignCrew::class);
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(CampaignWeek::class)->orderBy('week_number');
    }
}
