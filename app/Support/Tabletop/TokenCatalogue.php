<?php

namespace App\Support\Tabletop;

use App\Models\Token;

/** The tokens a model can carry (Focus, Shielded and so on), for the tabletop's model panel. */
class TokenCatalogue
{
    /**
     * @return list<array{id: int, name: string}>
     */
    public static function options(): array
    {
        return Token::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Token $t) => ['id' => $t->id, 'name' => $t->name])
            ->all();
    }
}
