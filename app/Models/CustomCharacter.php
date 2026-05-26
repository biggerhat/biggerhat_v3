<?php

namespace App\Models;

use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Observers\CustomCharacterObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperCustomCharacter
 */
#[ObservedBy(CustomCharacterObserver::class)]
class CustomCharacter extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
            'second_faction' => FactionEnum::class,
            'station' => CharacterStationEnum::class,
            'base' => BaseSizeEnum::class,
            'defense_suit' => SuitEnum::class,
            'willpower_suit' => SuitEnum::class,
            'generates_stone' => 'boolean',
            'is_unhirable' => 'boolean',
            'is_public' => 'boolean',
            'actions' => 'array',
            'abilities' => 'array',
            'keywords' => 'array',
            'characteristics' => 'array',
            'linked_crew_upgrades' => 'array',
            'linked_totems' => 'array',
            // Campaign-mode extension flags. Booleans cast via stringly-typed
            // SQLite columns; without these the `current` check returns 0/1
            // ints and trips equality comparisons.
            'is_campaign_leader' => 'boolean',
            'is_campaign_totem' => 'boolean',
            'miraculous_recovery_used' => 'boolean',
            'current' => 'boolean',
            'annihilated_at' => 'datetime',
            'replaced_at' => 'datetime',
        ];
    }

    protected $appends = [
        'faction_color',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomCharacter $character) {
            if (! $character->share_code) {
                $character->share_code = Str::random(12);
            }
        });
    }

    public function getFactionColorAttribute(): string
    {
        return $this->faction->color();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
