<?php

namespace App\Models;

use App\Enums\ModelStageEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One physical model in a user's collection: a copy of a miniature and how far along it is.
 *
 * @mixin IdeHelperCollectionModel
 */
class CollectionModel extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['stage' => ModelStageEnum::class];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Miniature, $this>
     */
    public function miniature(): BelongsTo
    {
        return $this->belongsTo(Miniature::class);
    }
}
