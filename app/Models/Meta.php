<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A "meta" — a regional / community grouping of players (e.g. "Boston",
 * "PNW", "FB Online"). Used for Round 1 same-meta-avoidance pairing and
 * for displaying community affiliation on player profiles.
 *
 * @mixin IdeHelperMeta
 */
class Meta extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $meta) {
            if (! $meta->slug) {
                $meta->slug = Str::slug($meta->name);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tournamentPlayers(): HasMany
    {
        return $this->hasMany(TournamentPlayer::class);
    }

    /**
     * Find an existing meta by name (case-insensitive, trimmed) or create one.
     * Returns the existing or new model. Use this from controllers to keep the
     * `metas` table self-curating.
     */
    public static function findOrCreateByName(string $name): self
    {
        $name = trim($name);
        $existing = self::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
        if ($existing) {
            return $existing;
        }

        return self::create(['name' => $name, 'slug' => Str::slug($name)]);
    }
}
