<?php

namespace App\Models\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\AllegianceCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperAllegianceCard
 */
class AllegianceCard extends Model
{
    /** @use HasFactory<AllegianceCardFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_allegiance_cards';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function casts(): array
    {
        return [
            'type' => AllegianceTypeEnum::class,
        ];
    }

    protected static function newFactory(): AllegianceCardFactory
    {
        return AllegianceCardFactory::new();
    }

    public function allegiance(): BelongsTo
    {
        return $this->belongsTo(Allegiance::class, 'allegiance_id');
    }

    public function abilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'tos_allegiance_card_ability', 'allegiance_card_id', 'ability_id')
            ->withPivot('sort_order')
            ->orderBy('tos_allegiance_card_ability.sort_order');
    }
}
