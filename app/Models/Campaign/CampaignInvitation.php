<?php

namespace App\Models\Campaign;

use App\Models\User;
use Database\Factories\Campaign\CampaignInvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Pending invitation. Either keyed to an existing user (typical) or by email
 * (for someone without an account yet). Token is the URL-safe identifier the
 * invitee uses to land on the accept page.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int|null $user_id
 * @property string|null $email
 * @property string $token
 * @property \Carbon\CarbonImmutable|null $accepted_at
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property-read Campaign $campaign
 * @property-read \App\Models\User|null $user
 * @mixin IdeHelperCampaignInvitation
 */
class CampaignInvitation extends Model
{
    /** @use HasFactory<CampaignInvitationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'accepted_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    protected static function booted(): void
    {
        static::creating(function (CampaignInvitation $invitation) {
            if (! $invitation->token) {
                $invitation->token = Str::random(32);
            }
        });
    }

    protected static function newFactory(): CampaignInvitationFactory
    {
        return CampaignInvitationFactory::new();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
