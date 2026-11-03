<?php

namespace App\Models;

use App\Enums\FactionEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Jobs\Campaign\GenerateCustomUpgradeCardImage;
use App\Observers\CustomUpgradeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @property FactionEnum|null $faction
 * @property \Carbon\CarbonInterface|null $card_image_generated_at set only when CustomUpgradeCardImageGenerator finishes — the cache-bust signal for the saved images
 * @property bool|null $campaign_still_live Virtual attribute, not a DB column — set by
 *                                          CustomCharacterController::index() only, batched per-request against the
 *                                          linked CampaignCrew's campaign status. Absent (null) anywhere else.
 *
 * @mixin IdeHelperCustomUpgrade
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
            'is_campaign_crew_card' => 'boolean',
            'content_blocks' => 'array',
            'back_tokens' => 'array',
            'back_markers' => 'array',
            'card_image_generated_at' => 'datetime',
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

    /**
     * Queues the saved-card render ~20s out; a burst of edits shares one waiting job (the marker is released when the
     * job starts, so a later edit queues again). Same scheme as CustomCharacter::queueCardImage().
     */
    public function queueCardImage(): void
    {
        if (! Cache::add(self::renderPendingKey($this->id), true, 60)) {
            return;
        }

        GenerateCustomUpgradeCardImage::dispatch($this->id)->delay(now()->addSeconds(20))->afterCommit();
    }

    /** True while a render is waiting for its delay or running — drives the "Rendering…" state. */
    public function cardImagePending(): bool
    {
        return Cache::has(self::renderPendingKey($this->id)) || Cache::has(self::renderingKey($this->id));
    }

    public static function renderPendingKey(int $id): string
    {
        return "custom-upgrade-render-pending:{$id}";
    }

    public static function renderingKey(int $id): string
    {
        return "custom-upgrade-rendering:{$id}";
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
