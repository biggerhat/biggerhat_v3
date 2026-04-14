<?php

namespace App\Observers;

use App\Models\CustomCharacter;
use Illuminate\Support\Str;

class CustomCharacterObserver
{
    public function creating(CustomCharacter $character): void
    {
        $character->display_name = $character->name;
        if ($character->title) {
            $character->display_name .= ", {$character->title}";
        }

        $base = Str::slug($character->display_name);
        $slug = $base;
        $i = 1;
        while (CustomCharacter::where('slug', $slug)->where('user_id', $character->user_id)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        $character->slug = $slug;
    }

    public function updating(CustomCharacter $character): void
    {
        if ($character->isDirty('name') || $character->isDirty('title')) {
            $character->display_name = $character->name;
            if ($character->title) {
                $character->display_name .= ", {$character->title}";
            }

            $base = Str::slug($character->display_name);
            $slug = $base;
            $i = 1;
            while (CustomCharacter::where('slug', $slug)->where('user_id', $character->user_id)->where('id', '!=', $character->id)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            $character->slug = $slug;
        }
    }
}
