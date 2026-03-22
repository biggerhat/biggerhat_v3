<?php

namespace App\Models;

use App\Enums\FactionEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperCrewBuild
 */
class CrewBuild extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
            'description' => 'array',
            'crew_data' => 'array',
            'encounter_size' => 'integer',
            'is_archived' => 'boolean',
            'is_public' => 'boolean',
            'references' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CrewBuild $build) {
            if (! $build->share_code) {
                $build->share_code = Str::random(12);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'master_id');
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(CrewBuild::class, 'copied_from_id');
    }

    public function crewUpgrade(): BelongsTo
    {
        return $this->belongsTo(Upgrade::class, 'crew_upgrade_id');
    }

    /**
     * Compute reference data (markers, tokens, upgrades, linked characters) for a set of character IDs.
     */
    public static function computeReferences(array $characterIds): array
    {
        if (empty($characterIds)) {
            return ['markers' => [], 'tokens' => [], 'upgrades' => [], 'characters' => []];
        }

        $characters = Character::with([
            'markers', 'tokens', 'characterUpgrades',
            'summons.miniatures', 'replacesInto.miniatures',
        ])->whereIn('id', $characterIds)->get();

        // Also gather references from summoned/replaced characters
        $linkedCharacterIds = $characters->flatMap(fn ($c) => $c->summons->pluck('id')
            ->merge($c->replacesInto->pluck('id'))
        )->unique()->diff($characterIds)->values();

        $linkedCharacters = $linkedCharacterIds->isNotEmpty()
            ? Character::with(['markers', 'tokens', 'characterUpgrades'])
                ->whereIn('id', $linkedCharacterIds)->get()
            : collect();

        $allCharacters = $characters->merge($linkedCharacters);

        $markers = $allCharacters->flatMap->markers->unique('id')->sortBy('name')->values()
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'slug' => $m->slug, 'description' => $m->description, 'base' => $m->base])
            ->toArray();

        $tokens = $allCharacters->flatMap->tokens->unique('id')->sortBy('name')->values()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug, 'description' => $t->description])
            ->toArray();

        $upgrades = $allCharacters->flatMap->characterUpgrades->unique('id')->sortBy('name')->values()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'slug' => $u->slug,
                'front_image' => $u->front_image,
                'back_image' => $u->back_image,
                'type' => $u->type?->label(),
            ])
            ->toArray();

        $linkedChars = collect();
        $mapLinked = function (Character $s, string $type) use ($linkedChars): void {
            $linkedChars->push([
                ...$s->only('id', 'display_name', 'slug', 'faction'),
                'type' => $type,
                'front_image' => $s->miniatures->first()?->front_image,
                'back_image' => $s->miniatures->first()?->back_image,
            ]);
        };
        foreach ($characters as $c) {
            $c->summons->each(fn (Character $s) => $mapLinked($s, 'Summons')); // @phpstan-ignore argument.type
            $c->replacesInto->each(fn (Character $s) => $mapLinked($s, 'Replaces into')); // @phpstan-ignore argument.type
        }

        return [
            'markers' => $markers,
            'tokens' => $tokens,
            'upgrades' => $upgrades,
            'characters' => $linkedChars->unique('id')->sortBy('display_name')->values()->toArray(),
        ];
    }

    /**
     * Recompute and save references based on current crew composition.
     */
    public function refreshReferences(): void
    {
        $allIds = array_merge([$this->master_id], $this->crew_data ?? []);

        // Include totem if master has one
        $master = Character::find($this->master_id);
        if ($master?->has_totem_id) {
            $allIds[] = $master->has_totem_id;
        }

        $this->update(['references' => self::computeReferences(array_filter($allIds))]);
    }
}
