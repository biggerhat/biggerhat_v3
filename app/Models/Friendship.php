<?php

namespace App\Models;

use Database\Factories\FriendshipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A friend request/friendship between two users. Binary pending↔accepted
 * state via a nullable `accepted_at` (mirrors CampaignInvitation) — declining
 * a request or unfriending just deletes the row, no separate "declined"
 * state. Directional (requester/addressee) rather than a symmetric pivot, so
 * "who sent it" is always known while pending.
 *
 * @property int $id
 * @property int $requester_id
 * @property int $addressee_id
 * @property \Carbon\CarbonImmutable|null $accepted_at
 * @property-read User $requester
 * @property-read User $addressee
 *
 * @mixin IdeHelperFriendship
 */
class Friendship extends Model
{
    /** @use HasFactory<FriendshipFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'accepted_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): FriendshipFactory
    {
        return FriendshipFactory::new();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at');
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->whereNotNull('accepted_at');
    }

    /**
     * A friendship row for a given pair, regardless of which side sent it.
     */
    public function scopeBetween(Builder $query, int $userIdA, int $userIdB): Builder
    {
        return $query->where(function (Builder $q) use ($userIdA, $userIdB) {
            $q->where(['requester_id' => $userIdA, 'addressee_id' => $userIdB])
                ->orWhere(['requester_id' => $userIdB, 'addressee_id' => $userIdA]);
        });
    }
}
