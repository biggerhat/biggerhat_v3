<?php

namespace App\Models\TOS;

use App\Enums\TOS\TriggerTimingEnum;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\TriggerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class, 'action_id');
    }
}
