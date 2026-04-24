<?php

namespace Database\Seeders\TOS;

use App\Enums\TOS\AllegianceEnum;
use App\Models\TOS\Allegiance;
use Illuminate\Database\Seeder;

class AllegianceSeeder extends Seeder
{
    public function run(): void
    {
        $sortOrder = 0;
        foreach (AllegianceEnum::cases() as $case) {
            Allegiance::updateOrCreate(
                ['slug' => $case->value],
                [
                    'name' => $case->label(),
                    'short_name' => $case->shortName(),
                    'type' => $case->type(),
                    'is_syndicate' => $case->isSyndicate(),
                    'logo_path' => $case->logo(),
                    'color_slug' => $case->color(),
                    'sort_order' => $sortOrder++,
                ]
            );
        }
    }
}
