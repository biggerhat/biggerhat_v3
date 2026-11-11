<?php

namespace App\Models;

use App\Enums\FactionEnum;
use App\Models\TOS\Allegiance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTournamentRsvp
 */
class TournamentRsvp extends Model
{
    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
        ];
    }

    public function allegiance(): BelongsTo
    {
        return $this->belongsTo(Allegiance::class, 'allegiance_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
