<?php

namespace App\Models\TOS;

use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\OperationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A TOS Operation (the scenario/objective a game is played for). Tournament rounds pick one;
 * rulebook source text is stored in sections so it can be shown on the round and in the admin.
 *
 * @mixin IdeHelperOperation
 */
class Operation extends Model
{
    /** @use HasFactory<OperationFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_operations';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function newFactory(): OperationFactory
    {
        return OperationFactory::new();
    }

    /** Operation names are unique, so slugs are deterministic permalinks. */
    protected static function slugNeedsRandomSuffix(): bool
    {
        return false;
    }
}
