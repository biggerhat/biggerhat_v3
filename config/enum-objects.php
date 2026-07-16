<?php

return [

    /*
    | Which enums to generate, as: PHP namespace => folder containing them.
    |
    |    'App\\Enums' => 'app/Enums'
    |
    | Reads as "app/Enums/Status.php contains App\Enums\Status".
    | Subfolders are scanned too and mirror into the output path.
    */
    'paths' => [
        'App\\Enums' => 'app/Enums',
    ],

    /*
    | Generated files land here, plus a .enum-objects.json manifest.
    | Placed under the existing resources/js/types/ convention (see game.ts,
    | tournament.ts) rather than a sibling top-level directory, so there's one
    | home for enum-shaped TS, not two differently-named ones.
    */
    'output_path' => 'resources/js/types/generated',

    /*
    | Output format: 'ts' or 'json'.
    */
    'format' => 'ts',

    /*
    | Method called on each case for its label when the enum defines it.
    | Enums without it fall back to Str::headline() of the case name.
    */
    'label_method' => 'label',

    /*
    | Output keys for the three built-in properties on every case object.
    | Set one to null to leave it out of the generated objects entirely.
    */
    'name_key' => 'name',
    'value_key' => 'value',
    'label_key' => 'label',
];
