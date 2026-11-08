<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A push token registered by the native app on one of a user's devices.
 *
 * @property int $id
 * @property int $user_id
 * @property string $platform ios|android
 * @property string $token
 * @property \Carbon\CarbonInterface|null $last_seen_at
 * @property-read User $user
 */
class DeviceToken extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceTokenFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
