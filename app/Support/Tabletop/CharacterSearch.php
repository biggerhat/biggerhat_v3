<?php

namespace App\Support\Tabletop;

use App\Models\Character;

/** Finds Malifaux character cards for the tabletop sandbox, with what the board needs from each: base size, Sz and Sp. */
class CharacterSearch
{
    public const LIMIT = 12;

    /**
     * Every word must appear in the card's name (any order); names that start with the text come first.
     *
     * @return list<array{id: int, name: string, faction: string, faction_key: string, station: string|null, base_mm: int, sz: int, sp: int, health: int, df: int|null, wp: int|null, front_image: string|null, back_image: string|null}>
     */
    public static function find(string $query): array
    {
        $words = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return [];
        }

        $builder = Character::query()->standard()->where('is_hidden', false);
        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $builder->where('display_name', 'like', $like);
        }

        return $builder
            ->orderByRaw('display_name like ? desc', [addcslashes(trim($query), '%_\\').'%'])
            ->orderBy('display_name')
            ->limit(self::LIMIT)
            ->with('miniatures:id,character_id,front_image,back_image')
            ->get(['id', 'display_name', 'faction', 'station', 'base', 'size', 'speed', 'health', 'defense', 'willpower'])
            ->map(fn (Character $c) => [
                'id' => $c->id,
                'name' => $c->display_name,
                'faction' => $c->faction->label(),
                'faction_key' => (string) $c->getRawOriginal('faction'),
                'station' => $c->station?->value,
                'base_mm' => $c->base->value,
                'sz' => $c->size,
                'sp' => $c->speed,
                'health' => $c->health,
                // what the crew row shows for a model in the sandbox, as it does for a game's crew
                'df' => $c->defense,
                'wp' => $c->willpower,
                'front_image' => $c->miniatures->first()?->front_image,
                'back_image' => $c->miniatures->first()?->back_image,
            ])
            ->all();
    }
}
