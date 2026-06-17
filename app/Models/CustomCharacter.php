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
            'is_campaign_totem_template' => 'boolean',
            'is_campaign_black_joker_totem' => 'boolean',
            'is_campaign_red_joker_totem' => 'boolean',
            'campaign_is_black_joker_totem' => 'boolean',
            'campaign_is_red_joker_totem' => 'boolean',
            'campaign_totem_special_replace' => 'boolean',
            'campaign_is_mini_master' => 'boolean',
            'miraculous_recovery_used' => 'boolean',
            'current' => 'boolean',
            'annihilated_at' => 'datetime',
            'replaced_at' => 'datetime',
            'xp_track' => 'array',
            // Integer casts on campaign-mode stat columns. SQLite returns
            // strings for numeric columns by default, which trips comparison
            // operators (e.g. campaign_totem_flip_value === $flipped). Cast
            // them explicitly so reads return PHP ints.
            'campaign_health' => 'integer',
            'campaign_df' => 'integer',
            'campaign_wp' => 'integer',
            'campaign_sp' => 'integer',
            'campaign_size' => 'integer',
            'campaign_br' => 'integer',
            'campaign_totem_flip_value' => 'integer',
        ];
    }

    /**
     * Canonical 39-box Leadership Experience chart (rulebook pg 31, 13 + 13 + 13).
     * Tier numbers per the printed track; a null tier means the XP point is
     * earned without triggering an advancement at that position. Used to lazy-
     * initialize a leader's `xp_track` column on first read.
     *
     * @return array<int, array{index: int, filled: bool, tier: int|null}>
     */
    public static function defaultXpTrack(): array
    {
        $row1 = [1, 1, 2, null, 3, null, 4, null, 1, null, 2, null, 4];
        $row2 = [null, null, null, 1, null, null, 2, 1, null, null, null, 3, null];
        $row3 = [null, null, 1, null, null, null, null, 2, null, null, null, null, 4];

        $track = [];
        $index = 0;
        foreach (array_merge($row1, $row2, $row3) as $tier) {
            $track[] = ['index' => $index, 'filled' => false, 'tier' => $tier];
            $index++;
        }

        return $track;
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
