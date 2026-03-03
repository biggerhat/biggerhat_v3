<?php

namespace Database\Seeders;

use App\Enums\UpgradeDomainTypeEnum;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use App\Models\Marker;
use App\Models\Miniature;
use App\Models\Upgrade;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class CharacterSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureStorageLink();
        $this->downloadPlaceholderImages();

        $keywords = Keyword::factory()->count(15)->create();
        $characteristics = Characteristic::factory()->count(10)->create();
        $markers = Marker::factory()->count(8)->create();

        $characterUpgrades = Upgrade::factory()
            ->count(8)
            ->state(['domain' => UpgradeDomainTypeEnum::Character])
            ->create();

        $crewUpgrades = Upgrade::factory()
            ->count(4)
            ->state(['domain' => UpgradeDomainTypeEnum::Crew])
            ->create();

        $characters = Character::factory()->count(40)->create();

        foreach ($characters as $character) {
            Miniature::factory()
                ->count(random_int(1, 2))
                ->state(['character_id' => $character->id])
                ->create();

            $character->keywords()->attach(
                $keywords->random(random_int(1, 3))->pluck('id')->toArray()
            );

            if (random_int(0, 1)) {
                $character->characteristics()->attach(
                    $characteristics->random(random_int(1, 2))->pluck('id')->toArray()
                );
            }

            if (random_int(0, 1)) {
                $character->markers()->attach(
                    $markers->random(random_int(1, 2))->pluck('id')->toArray()
                );
            }

            if (random_int(0, 1)) {
                $character->upgrades()->attach(
                    $characterUpgrades->random(random_int(1, 2))->pluck('id')->toArray()
                );
            }
        }
    }

    private function ensureStorageLink(): void
    {
        Artisan::call('storage:link');
    }

    private function downloadPlaceholderImages(): void
    {
        $seedDir = storage_path('app/public/seed');
        File::ensureDirectoryExists($seedDir);

        $images = [
            'card-front.png' => 'https://placehold.co/300x420/374151/FFFFFF/png?text=Card+Front',
            'card-back.png' => 'https://placehold.co/300x420/1f2937/FFFFFF/png?text=Card+Back',
            'upgrade-front.png' => 'https://placehold.co/300x420/7c3aed/FFFFFF/png?text=Upgrade+Front',
            'upgrade-back.png' => 'https://placehold.co/300x420/5b21b6/FFFFFF/png?text=Upgrade+Back',
        ];

        foreach ($images as $filename => $url) {
            $path = $seedDir.'/'.$filename;
            if (! File::exists($path)) {
                $contents = @file_get_contents($url);
                if ($contents !== false) {
                    File::put($path, $contents);
                }
            }
        }
    }
}
