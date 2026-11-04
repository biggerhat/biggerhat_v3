<?php

namespace App\Models;

use App\Enums\FactionEnum;
use Database\Factories\CardArtFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

/**
 * One piece of admin-curated character art that Custom Card Creator characters can pick.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property FactionEnum|null $faction
 * @property string|null $category
 * @property int|null $character_id official character this art depicts (optional; only used to suggest it in the editor)
 * @property string $image_path path on the public disk (e.g. card_arts/foo_uuid.webp)
 * @property string|null $credit
 * @property bool $is_active
 * @property int $sort_order
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomCharacter> $customCharacters
 */
class CardArt extends Model
{
    /** @use HasFactory<CardArtFactory> */
    use HasFactory;

    protected $table = 'card_arts';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<CardArt>  $query
     * @return Builder<CardArt>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /** @return HasMany<CustomCharacter, $this> */
    public function customCharacters(): HasMany
    {
        return $this->hasMany(CustomCharacter::class, 'card_art_id');
    }

    /**
     * Active library art for a picker, plus the art a card already has even if it has since been retired.
     *
     * @return array<int, array{id: int, name: string, image_path: string, faction: string|null, category: string|null, character_id: int|null, character_name: string|null}>
     */
    public static function pickerOptions(?int $currentId = null): array
    {
        return static::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($currentId, fn ($qq) => $qq->orWhere('id', $currentId)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with('character:id,name,display_name')
            ->get(['id', 'name', 'image_path', 'faction', 'category', 'character_id'])
            ->map(fn (CardArt $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'image_path' => $a->image_path,
                'faction' => $a->faction?->value,
                'category' => $a->category,
                'character_id' => $a->character_id,
                'character_name' => $a->character?->display_name,
            ])
            ->all();
    }

    /**
     * Validation for the art + framing fields every card form shares. Only active art can be newly picked; null clears
     * it; the art a card already has stays valid after it is retired.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(?int $currentId = null): array
    {
        return [
            'card_art_id' => ['nullable', 'integer', Rule::exists('card_arts', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $currentId))],
            // Framing of the chosen art inside the card's art window (focus point in %, zoom in %, free offsets in %).
            'card_art_x' => ['nullable', 'integer', 'min:0', 'max:100'],
            'card_art_y' => ['nullable', 'integer', 'min:0', 'max:100'],
            'card_art_zoom' => ['nullable', 'integer', 'min:50', 'max:300'],
            'card_art_dx' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'card_art_dy' => ['nullable', 'integer', 'min:-100', 'max:100'],
        ];
    }

    /**
     * The framing columns are NOT NULL with defaults, so a null (or clearing the art) maps back to the default
     * framing; keys the client didn't send are left alone.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function normalizeFraming(array $validated): array
    {
        $defaults = ['card_art_x' => 50, 'card_art_y' => 50, 'card_art_zoom' => 100, 'card_art_dx' => 0, 'card_art_dy' => 0];

        foreach ($defaults as $key => $default) {
            if (array_key_exists($key, $validated)) {
                $validated[$key] ??= $default;
            }
        }

        // No art, no framing to keep.
        if (array_key_exists('card_art_id', $validated) && $validated['card_art_id'] === null) {
            $validated = array_merge($validated, $defaults);
        }

        return $validated;
    }
}
