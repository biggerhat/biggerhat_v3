<?php

namespace App\Support\Tabletop;

use App\Models\TabletopImage;
use Illuminate\Support\Facades\Storage;

/**
 * What the tabletop can draw: the table surfaces to choose from and the texture for each kind of terrain. The app ships its own
 * (original SVGs under public/images/tabletop); active images from the admin library are offered next to the table surfaces and
 * replace the default for a terrain kind.
 */
class TabletopImages
{
    /** Table surfaces that ship with the app: key => label. */
    public const DEFAULT_TABLES = [
        'felt' => 'Green felt',
        'cobble' => 'Cobbled street',
        'wood' => 'Wooden boards',
        'dust' => 'Dusty ground',
        'slate' => 'Slate floor',
    ];

    /** Terrain kinds (the preset keys the board knows) that have a default texture: key => label. */
    public const TERRAIN_KINDS = [
        'building' => 'Building',
        'tower' => 'Tower',
        'steep' => 'Steep building',
        'wall' => 'Wall',
        'fence' => 'Fence',
        'crates' => 'Crates',
        'hill' => 'Hill',
        'bridge' => 'Bridge',
        'fog' => 'Fog bank',
        'block' => 'Block',
        'round' => 'Round feature',
        'woods' => 'Woods',
    ];

    /**
     * Table surfaces to offer, the built-in ones first. `fit` says how the picture is drawn: a built-in one repeats as a tile, an
     * uploaded one stretches over the whole 36" table.
     *
     * @return list<array{key: string, name: string, url: string, fit: string}>
     */
    public static function tables(): array
    {
        $tables = [];
        foreach (self::DEFAULT_TABLES as $key => $label) {
            $tables[] = ['key' => $key, 'name' => $label, 'url' => "/images/tabletop/tables/{$key}.svg", 'fit' => 'tile'];
        }

        $uploaded = TabletopImage::query()->active()->where('kind', TabletopImage::KIND_TABLE)->orderBy('sort_order')->orderBy('name')->get();
        foreach ($uploaded as $image) {
            $tables[] = ['key' => 'i'.$image->id, 'name' => $image->name, 'url' => Storage::disk('public')->url($image->image_path), 'fit' => 'cover'];
        }

        return $tables;
    }

    /** @return list<string> */
    public static function tableKeys(): array
    {
        return array_column(self::tables(), 'key');
    }

    /**
     * The texture for each terrain kind: the built-in one, unless an active image in the library is for that kind (the first by sort
     * order wins).
     *
     * @return array<string, string> kind => url
     */
    public static function terrain(): array
    {
        $map = [];
        foreach (self::TERRAIN_KINDS as $key => $label) {
            $map[$key] = "/images/tabletop/terrain/{$key}.svg";
        }

        $uploaded = TabletopImage::query()->active()->where('kind', TabletopImage::KIND_TERRAIN)->orderByDesc('sort_order')->orderByDesc('id')->get();
        foreach ($uploaded as $image) {
            if ($image->terrain_key !== null && isset(self::TERRAIN_KINDS[$image->terrain_key])) {
                $map[$image->terrain_key] = Storage::disk('public')->url($image->image_path);
            }
        }

        return $map;
    }
}
