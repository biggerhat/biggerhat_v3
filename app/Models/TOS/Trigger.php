<?php

namespace App\Models\TOS;

use App\Enums\TOS\TriggerTimingEnum;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\TriggerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperTrigger
 */
class Trigger extends Model
{
    /** @use HasFactory<TriggerFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_triggers';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'timing' => TriggerTimingEnum::class,
        ];
    }

    protected static function newFactory(): TriggerFactory
    {
        return TriggerFactory::new();
    }

    /**
     * Detach from every Action on delete so the pivot doesn't orphan
     * (matches the DB-level cascade; kept explicit for the SQLite test
     * env that runs with FK enforcement off).
     */
    protected static function booted(): void
    {
        static::deleting(function (self $trigger) {
            $trigger->actions()->detach();
        });
    }

    /**
     * Triggers are frequently shared across multiple Actions (e.g. "Critical"
     * appears on every Melee action). Stored via the `tos_action_trigger`
     * pivot with ordering.
     */
    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'tos_action_trigger', 'trigger_id', 'action_id')
            ->withPivot('sort_order')
            ->orderBy('tos_action_trigger.sort_order');
    }
}
