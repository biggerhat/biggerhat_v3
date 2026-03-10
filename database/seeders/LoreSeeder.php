<?php

namespace Database\Seeders;

use App\Enums\LoreMediaTypeEnum;
use App\Models\Character;
use App\Models\Lore;
use App\Models\LoreMedia;
use Illuminate\Database\Seeder;

class LoreSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMedia();
        $this->seedLore();
    }

    private function seedMedia(): void
    {
        $media = [
            ['name' => 'Malifaux Core Rulebook (M3E)', 'type' => LoreMediaTypeEnum::Rulebook, 'link' => null],
            ['name' => 'Malifaux Burns', 'type' => LoreMediaTypeEnum::FactionBook, 'link' => null],
            ['name' => 'Malifaux Madness', 'type' => LoreMediaTypeEnum::FactionBook, 'link' => null],
            ['name' => 'Shifting Loyalties', 'type' => LoreMediaTypeEnum::FactionBook, 'link' => null],
            ['name' => 'Broken Promises', 'type' => LoreMediaTypeEnum::FactionBook, 'link' => null],
            ['name' => 'The Other Side Core Rulebook', 'type' => LoreMediaTypeEnum::Rulebook, 'link' => null],
            ['name' => 'Chronicles Volume 1', 'type' => LoreMediaTypeEnum::Chronicle, 'link' => null],
            ['name' => 'Chronicles Volume 2', 'type' => LoreMediaTypeEnum::Chronicle, 'link' => null],
            ['name' => 'Penny Dreadful: In Defense of Innocence', 'type' => LoreMediaTypeEnum::PennyDreadful, 'link' => null],
            ['name' => 'Penny Dreadful: Night in Rottenburg', 'type' => LoreMediaTypeEnum::PennyDreadful, 'link' => null],
            ['name' => 'Bayou Broadcast #1', 'type' => LoreMediaTypeEnum::Broadcast, 'link' => null],
            ['name' => 'Wyrd News - January 2024', 'type' => LoreMediaTypeEnum::WyrdNews, 'link' => null],
            ['name' => 'Book 1: Rising Powers', 'type' => LoreMediaTypeEnum::FactionBook, 'link' => null],
            ['name' => 'Book 2: Storm of Shadows', 'type' => LoreMediaTypeEnum::FactionBook, 'link' => null],
            ['name' => 'Ripples of Fate', 'type' => LoreMediaTypeEnum::FactionBook, 'link' => null],
        ];

        foreach ($media as $data) {
            LoreMedia::create($data);
        }
    }

    private function seedLore(): void
    {
        $mediaByName = LoreMedia::all()->keyBy('name');

        $lores = [
            [
                'name' => 'The Breach Opens',
                'media' => 'Malifaux Core Rulebook (M3E)',
                'characters' => [],
            ],
            [
                'name' => 'The Governor-General\'s Welcome',
                'media' => 'Malifaux Core Rulebook (M3E)',
                'characters' => ['Lady Justice', 'Perdita Ortega'],
            ],
            [
                'name' => 'Burning of Malifaux',
                'media' => 'Malifaux Burns',
                'characters' => ['Sonnia Criid'],
            ],
            [
                'name' => 'The Arcanist Manifesto',
                'media' => 'Malifaux Core Rulebook (M3E)',
                'characters' => ['Rasputina', 'Marcus'],
            ],
            [
                'name' => 'Bayou Troubles',
                'media' => 'Bayou Broadcast #1',
                'characters' => ['Som\'er Teeth Jones', 'Ophelia LaCroix'],
            ],
            [
                'name' => 'The Redchapel Killer',
                'media' => 'Malifaux Core Rulebook (M3E)',
                'characters' => ['Seamus'],
            ],
            [
                'name' => 'Rise of the Ten Thunders',
                'media' => 'Book 2: Storm of Shadows',
                'characters' => ['Misaki Katanaka', 'Jakob Lynch'],
            ],
            [
                'name' => 'Neverborn Awakening',
                'media' => 'Malifaux Core Rulebook (M3E)',
                'characters' => ['Pandora', 'Lilith'],
            ],
            [
                'name' => 'The Plague Spreads',
                'media' => 'Malifaux Burns',
                'characters' => ['Hamelin'],
            ],
            [
                'name' => 'Miners and Steamfitters Union',
                'media' => 'Book 1: Rising Powers',
                'characters' => ['Mei Feng'],
            ],
            [
                'name' => 'The Resurrectionists\' Gambit',
                'media' => 'Broken Promises',
                'characters' => ['Nicodem', 'Molly Squidpiddge'],
            ],
            [
                'name' => 'Into the Bayou',
                'media' => 'Ripples of Fate',
                'characters' => ['Zipp', 'Brewmaster'],
            ],
            [
                'name' => 'Shifting Loyalties',
                'media' => 'Shifting Loyalties',
                'characters' => ['Tara', 'Jack Daw'],
            ],
            [
                'name' => 'The Explorer\'s Society Founded',
                'media' => 'Malifaux Burns',
                'characters' => ['Lord Cooper', 'English Ivan'],
            ],
            [
                'name' => 'Defense of Innocence',
                'media' => 'Penny Dreadful: In Defense of Innocence',
                'characters' => ['Hoffman'],
            ],
            [
                'name' => 'A Night in Rottenburg',
                'media' => 'Penny Dreadful: Night in Rottenburg',
                'characters' => ['Von Schtook'],
            ],
            [
                'name' => 'The Madness Begins',
                'media' => 'Malifaux Madness',
                'characters' => ['Dreamer', 'Zoraida'],
            ],
            [
                'name' => 'Von Schill\'s Mercenaries',
                'media' => 'Malifaux Core Rulebook (M3E)',
                'characters' => ['Von Schill'],
            ],
            [
                'name' => 'Kaeris and the Burning Man',
                'media' => 'The Other Side Core Rulebook',
                'characters' => ['Kaeris'],
            ],
            [
                'name' => 'Perdita\'s Hunt',
                'media' => 'Chronicles Volume 1',
                'characters' => ['Perdita Ortega'],
            ],
        ];

        foreach ($lores as $data) {
            $media = $mediaByName[$data['media']] ?? null;
            if (! $media) {
                continue;
            }

            $lore = Lore::create([
                'name' => $data['name'],
                'lore_media_id' => $media->id,
            ]);

            if (! empty($data['characters'])) {
                $characters = Character::whereIn('name', $data['characters'])->get();
                $lore->characters()->sync($characters->pluck('id'));
            }
        }
    }
}
