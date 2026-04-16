<?php

namespace App\Models;

use App\Enums\PodSourceEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperPodLink
 */
class PodLink extends Model
{
    protected $guarded = ['id'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->slug = Str::slug($model->name);
        });

        static::updating(function (self $model) {
            $model->slug = Str::slug($model->name);
        });
    }

    public function casts(): array
    {
        return [
            'source' => PodSourceEnum::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function miniatures(): MorphToMany
    {
        return $this->morphedByMany(Miniature::class, 'taggable', 'pod_link_taggables');
    }

    public function upgrades(): MorphToMany
    {
        return $this->morphedByMany(Upgrade::class, 'taggable', 'pod_link_taggables');
    }

    public function keywords(): MorphToMany
    {
        return $this->morphedByMany(Keyword::class, 'taggable', 'pod_link_taggables');
    }

    /**
     * @return array<string>
     */
    public function getFactionTagsAttribute(): array
    {
        return DB::table('pod_link_faction')
            ->where('pod_link_id', $this->id)
            ->pluck('faction')
            ->toArray();
    }

    public function syncFactionTags(array $factions): void
    {
        DB::table('pod_link_faction')->where('pod_link_id', $this->id)->delete();

        $rows = collect($factions)->map(fn (string $faction) => [
            'pod_link_id' => $this->id,
            'faction' => $faction,
        ])->toArray();

        if (! empty($rows)) {
            DB::table('pod_link_faction')->insert($rows);
        }
    }
}
