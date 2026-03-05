<?php

namespace Database\Seeders;

use App\Enums\UpgradeDomainTypeEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use App\Models\Marker;
use App\Models\Miniature;
use App\Models\Package;
use App\Models\Trigger;
use App\Models\Upgrade;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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

        $actions = Action::factory()->count(30)->create();
        $triggers = Trigger::factory()->count(25)->create();
        $abilities = Ability::factory()->count(20)->create();

        // Attach 0-3 triggers to each action
        foreach ($actions as $action) {
            if (random_int(0, 1)) {
                $action->triggers()->attach(
                    $triggers->random(random_int(1, 3))->pluck('id')->toArray()
                );
            }
        }

        $characters = Character::factory()->count(40)->create();

        foreach ($characters as $character) {
            $miniatures = Miniature::factory()
                ->count(random_int(1, 2))
                ->state(['character_id' => $character->id])
                ->create();

            foreach ($miniatures as $miniature) {
                if ($miniature->name === null) {
                    $displayName = $character->display_name;
                    $miniature->update([
                        'display_name' => $displayName,
                        'slug' => Str::slug($displayName),
                    ]);
                }
            }

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

            // Attach 2-4 actions, with a chance one is a signature action
            $characterActions = $actions->random(random_int(2, 4));
            foreach ($characterActions as $action) {
                $character->actions()->attach($action->id, [
                    'is_signature_action' => random_int(1, 100) <= 15,
                ]);
            }

            // Attach 1-3 abilities
            $character->abilities()->attach(
                $abilities->random(random_int(1, 3))->pluck('id')->toArray()
            );
        }

        // Create packages and attach characters, miniatures, and keywords
        $allMiniatures = Miniature::all();
        $packages = Package::factory()->count(10)->create();

        foreach ($packages as $package) {
            $packageCharacters = $characters->random(random_int(1, 5));
            $package->characters()->attach($packageCharacters->pluck('id')->toArray());

            $packageMiniatures = $allMiniatures->random(min(random_int(1, 4), $allMiniatures->count()));
            $package->miniatures()->attach($packageMiniatures->pluck('id')->toArray());

            $package->keywords()->attach(
                $keywords->random(random_int(1, 3))->pluck('id')->toArray()
            );
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
