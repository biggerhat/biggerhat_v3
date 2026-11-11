<?php

namespace App\Models;

use App\Enums\FactionEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Garrison;
use Database\Factories\TournamentPlayerMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person on a team in a team event. The team itself is a TournamentPlayer row (the competitor that
 * gets paired, scored and ranked); its members carry the people-level data: name, linked account, and
 * what each declared (faction or allegiance + garrison).
 *
 * @mixin IdeHelperTournamentPlayerMember
 */
class TournamentPlayerMember extends Model
{
    /** @use HasFactory<TournamentPlayerMemberFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
        ];
    }

    protected static function newFactory(): TournamentPlayerMemberFactory
    {
        return TournamentPlayerMemberFactory::new();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(TournamentPlayer::class, 'tournament_player_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allegiance(): BelongsTo
    {
        return $this->belongsTo(Allegiance::class, 'allegiance_id');
    }

    public function garrison(): BelongsTo
    {
        return $this->belongsTo(Garrison::class, 'garrison_id');
    }
}
