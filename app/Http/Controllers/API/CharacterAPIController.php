<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Miniature;
use Illuminate\Http\Request;

class CharacterAPIController extends Controller
{
    public function view(Request $request)
    {
        $name = $request->get('name');

        $miniatures = Miniature::where('display_name', "%{$name}%")
            ->orWhereHas('character', function ($query) use ($name) {
                $query->where('nicknames', "%{$name}%");
            })
            ->with('character')->get();

        if (! $miniatures) {
            $miniatures = Miniature::where('display_name', 'LIKE', "%{$name}%")
                ->orWhereHas('character', function ($query) use ($name) {
                    $query->where('nicknames', 'LIKE', "%{$name}%");
                })
                ->with('character')->get();
        }

        return $miniatures;
    }

    public function images(Request $request)
    {
        $storageUrl = config('filesystems.disks.public.url').'/';

        return Character::whereHas('miniatures')
            ->with('miniatures')
            ->orderBy('display_name', 'ASC')
            ->get()
            ->map(function (Character $character) use ($storageUrl) {
                return [
                    'display_name' => $character->display_name,
                    'front_image' => $storageUrl.$character->miniatures()->first()->front_image,
                    'back_image' => $storageUrl.$character->miniatures()->first()->back_image,
                    'combination_image' => $storageUrl.$character->miniatures()->first()->combination_image,
                    'url' => route('characters.view', [
                        'character' => $character->slug,
                        'miniature' => $character->miniatures()->first()->id,
                        'slug' => $character->miniatures()->first()->slug,
                    ]),
                ];
            });
    }
}
