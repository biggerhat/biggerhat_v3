<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentRsvp extends Model
{
    protected $guarded = ['id'];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
