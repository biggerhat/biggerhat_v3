<?php

namespace App\Models;

use App\Enums\FactionEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Observers\CustomUpgradeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperCustomUpgrade
 *
 * @property FactionEnum|null $faction
 */
#[ObservedBy(CustomUpgradeObserver::class)]
class CustomUpgrade extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'domain' => UpgradeDomainTypeEnum::class,
            'faction' => FactionEnum::class,
            'is_public' => 'boolean',
            'content_blocks' => 'array',
            'back_tokens' => 'array',
            'back_markers' => 'array',
        ];
    }

    protected $appends = [
        'faction_color',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomUpgrade $upgrade) {
            if (! $upgrade->share_code) {
                $upgrade->share_code = Str::random(12);
            }
        });
    }

    public function getFactionColorAttribute(): ?string
    {
        return $this->faction?->color();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
