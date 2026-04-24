<?php

namespace App\Models\TOS;

use App\Enums\TOS\ActionTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Junction row for tos_action_types. One row per (Action, ActionTypeEnum)
 * pair — supports rulebook p. 22's "some Actions are a combination of
 * several different Action Types."
 *
 * @mixin IdeHelperActionTypeLink
 */
class ActionTypeLink extends Model
{
    protected $table = 'tos_action_types';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'type' => ActionTypeEnum::class,
        ];
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class, 'action_id');
    }
}
