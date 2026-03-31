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

    /**
     * Bump this when the references schema changes to invalidate cached references.
     */
    public const REFERENCES_VERSION = 3;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
            'description' => 'array',
            'crew_data' => 'array',
            'miniature_selections' => 'array',
            'encounter_size' => 'integer',
            'is_archived' => 'boolean',
            'is_public' => 'boolean',
            'references' => 'array',
            'custom_references' => 'array',
            'custom_crew_data' => 'array',
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
            return ['version' => self::REFERENCES_VERSION, 'markers' => [], 'tokens' => [], 'upgrades' => [], 'characters' => []];
        }

        $characters = Character::with([
            'markers', 'tokens', 'characterUpgrades',
            'summons.miniatures', 'replacesInto.miniatures',
        ])->whereIn('id', $characterIds)->get();

        // Include totems from loaded characters (avoids extra query in refreshReferences)
        $totemIds = $characters->pluck('has_totem_id')->filter()->diff($characterIds)->unique()->values();
        if ($totemIds->isNotEmpty()) {
            $totems = Character::with([
                'markers', 'tokens', 'characterUpgrades',
                'summons.miniatures', 'replacesInto.miniatures',
            ])->whereIn('id', $totemIds)->get();
            $characters = $characters->merge($totems);
        }

        // Also gather references from summoned/replaced characters
        $linkedCharacterIds = $characters->flatMap(fn ($c) => $c->summons->pluck('id')
            ->merge($c->replacesInto->pluck('id'))
        )->unique()->diff($characters->pluck('id'))->values();

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
                'miniatures' => $s->miniatures->map(fn ($m) => [
                    'id' => $m->id,
                    'display_name' => $m->display_name,
                    'front_image' => $m->front_image,
                    'back_image' => $m->back_image,
                ])->values()->toArray(),
            ]);
        };
        foreach ($characters as $c) {
            $c->summons->each(fn (Character $s) => $mapLinked($s, 'Summons'));
            $c->replacesInto->each(fn (Character $s) => $mapLinked($s, 'Replaces into'));
        }

        return [
            'version' => self::REFERENCES_VERSION,
            'markers' => $markers,
            'tokens' => $tokens,
            'upgrades' => $upgrades,
            'characters' => $linkedChars->unique('id')->sortBy('display_name')->values()->toArray(),
        ];
    }

    /**
     * Check if stored references are present and up-to-date.
     */
    public function hasValidReferences(): bool
    {
        return is_array($this->references)
            && ($this->references['version'] ?? 0) === self::REFERENCES_VERSION;
    }

    /**
     * Ensure references are computed and up-to-date. Rebuilds if missing or stale.
     */
    public function ensureReferences(): void
    {
        if (! $this->hasValidReferences()) {
            $this->refreshReferences();
        }
    }

    /**
     * Recompute and save references based on current crew composition, merged with custom references.
     */
    public function refreshReferences(): void
    {
        $allIds = array_filter(array_merge([$this->master_id], $this->crew_data ?? []));
        $computed = self::computeReferences($allIds);

        // Merge custom references (user-added items not derivable from crew composition)
        /** @var array $custom */
        $custom = $this->custom_references ?? [];
        if (! empty($custom)) {
            $computed = self::mergeCustomReferences($computed, $custom);
        }

        $this->update(['references' => $computed]);
    }

    /**
     * Merge custom reference items into computed references, avoiding duplicates.
     */
    private static function mergeCustomReferences(array $computed, array $custom): array
    {
        foreach (['characters', 'upgrades', 'markers', 'tokens'] as $type) {
            $customItems = $custom[$type] ?? [];
            if (empty($customItems)) {
                continue;
            }
            $existingIds = collect($computed[$type])->pluck('id')->toArray();
            foreach ($customItems as $item) {
                if (! in_array($item['id'], $existingIds)) {
                    $computed[$type][] = $item;
                }
            }
        }

        return $computed;
    }
}
