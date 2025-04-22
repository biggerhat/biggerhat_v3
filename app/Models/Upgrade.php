<?php

namespace App\Models;

use App\Enums\UpgradeTypeEnum;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Upgrade extends Model
{
    /** @use HasFactory<\Database\Factories\UpgradeFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'type' => UpgradeTypeEnum::class,
        ];
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'master_id', 'id');
    }

    public function markers(): MorphToMany
    {
        return $this->morphedByMany(Marker::class, 'upgradeable');
    }

    public function tokens(): MorphToMany
    {
        return $this->morphedByMany(Token::class, 'upgradeable');
    }

    public function actions(): MorphToMany
    {
        return $this->morphedByMany(Action::class, 'upgradeable');
    }

    public function abilities(): MorphToMany
    {
        return $this->morphedByMany(Ability::class, 'upgradeable');
    }

    public function triggers(): MorphToMany
    {
        return $this->morphedByMany(Trigger::class, 'upgradeable');
    }
}
