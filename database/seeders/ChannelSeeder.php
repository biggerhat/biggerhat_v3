<?php

namespace Database\Seeders;

use App\Enums\ContentTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\TransmissionTypeEnum;
use App\Models\Channel;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Transmission;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChannelSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create(['name' => 'Channel Owner']);
        $characters = Character::take(10)->get();
        $keywords = Keyword::take(8)->get();

        // Channel 1: YouTube focused
        $channel1 = Channel::create([
            'name' => 'Third Floor Wars',
            'description' => 'Weekly Malifaux battle reports, painting tutorials, and deep dives into crew strategies. Broadcasting from the third floor since 2019.',
        ]);
        $channel1->users()->attach($user->id);

        // Channel 2: Podcast
        $channel2 = Channel::create([
            'name' => 'Breachside Broadcast',
            'description' => 'A Malifaux podcast covering the latest meta shifts, tournament recaps, and lore discussions. New episodes every Tuesday.',
        ]);
        $channel2->users()->attach($user->id);

        // Channel 3: Blog/Website
        $channel3 = Channel::create([
            'name' => 'Soulstone Quarterly',
            'description' => 'In-depth written articles about Malifaux strategy, lore analysis, and community spotlights.',
        ]);

        // Channel 4: Mixed
        $channel4 = Channel::create([
            'name' => 'Bayou Broadcasting Co.',
            'description' => 'Gremlin-approved content covering all things Bayou and beyond. Videos, articles, and the occasional podcast.',
        ]);

        // --- Transmissions for Channel 1: Third Floor Wars ---

        $t1 = Transmission::create([
            'title' => 'Arcanists vs Guild — 50ss Battle Report',
            'description' => 'A full 50 soulstone battle report featuring Arcanists keyword synergy against a Guild gunline crew.',
            'url' => 'https://www.youtube.com/watch?v=example1',
            'channel_id' => $channel1->id,
            'transmission_type' => TransmissionTypeEnum::YouTube,
            'content_type' => ContentTypeEnum::BattleReports,
            'factions' => [FactionEnum::Arcanists->value, FactionEnum::Guild->value],
            'release_date' => now()->subDays(2),
        ]);
        $this->attachTags($t1, $characters, $keywords, [0, 1], [0, 1]);

        $t2 = Transmission::create([
            'title' => 'Master Deep Dive: Rasputina',
            'description' => 'Everything you need to know about running Rasputina in the current meta. Crew composition, upgrade choices, and matchup tips.',
            'url' => 'https://www.youtube.com/watch?v=example2',
            'channel_id' => $channel1->id,
            'transmission_type' => TransmissionTypeEnum::YouTube,
            'content_type' => ContentTypeEnum::DeepDives,
            'factions' => [FactionEnum::Arcanists->value],
            'release_date' => now()->subDays(7),
        ]);
        $this->attachTags($t2, $characters, $keywords, [0], [0, 2]);

        $t3 = Transmission::create([
            'title' => 'Neverborn vs Outcasts — Tournament Round 3',
            'description' => 'High stakes round 3 from the regional qualifier. Watch as Neverborn tricks clash with Outcast brute force.',
            'url' => 'https://www.youtube.com/watch?v=example3',
            'channel_id' => $channel1->id,
            'transmission_type' => TransmissionTypeEnum::YouTube,
            'content_type' => ContentTypeEnum::BattleReports,
            'factions' => [FactionEnum::Neverborn->value, FactionEnum::Outcasts->value],
            'release_date' => now()->subDays(14),
        ]);
        $this->attachTags($t3, $characters, $keywords, [2, 3], [3]);

        // --- Transmissions for Channel 2: Breachside Broadcast ---

        $t4 = Transmission::create([
            'title' => 'Episode 47: Post-Errata Meta Shakeup',
            'description' => 'We discuss how the latest errata has reshuffled the competitive landscape. Which masters rose? Which fell?',
            'url' => 'https://podcasts.example.com/breachside/47',
            'channel_id' => $channel2->id,
            'transmission_type' => TransmissionTypeEnum::Podcast,
            'content_type' => ContentTypeEnum::DeepDives,
            'factions' => [],
            'release_date' => now()->subDays(1),
        ]);

        $t5 = Transmission::create([
            'title' => 'Episode 46: The Lore of the Burning Man',
            'description' => 'A deep dive into the lore behind the Burning Man and its impact on the world of Malifaux.',
            'url' => 'https://podcasts.example.com/breachside/46',
            'channel_id' => $channel2->id,
            'transmission_type' => TransmissionTypeEnum::Podcast,
            'content_type' => ContentTypeEnum::Lore,
            'factions' => [],
            'release_date' => now()->subDays(8),
        ]);

        $t6 = Transmission::create([
            'title' => 'Episode 45: Ten Thunders Crew Building 101',
            'description' => 'Our resident Ten Thunders expert walks through building effective dual-faction crews.',
            'url' => 'https://podcasts.example.com/breachside/45',
            'channel_id' => $channel2->id,
            'transmission_type' => TransmissionTypeEnum::Podcast,
            'content_type' => ContentTypeEnum::DeepDives,
            'factions' => [FactionEnum::TenThunders->value],
            'release_date' => now()->subDays(15),
        ]);
        $this->attachTags($t6, $characters, $keywords, [4, 5], [4]);

        // --- Transmissions for Channel 3: Soulstone Quarterly ---

        $t7 = Transmission::create([
            'title' => 'The History of the Guild: From Earth to Malifaux',
            'description' => 'An extensive look at the Guild faction from its founding through the current era. Part 1 of our faction lore series.',
            'url' => 'https://soulstonequarterly.example.com/guild-history',
            'channel_id' => $channel3->id,
            'transmission_type' => TransmissionTypeEnum::Website,
            'content_type' => ContentTypeEnum::Lore,
            'factions' => [FactionEnum::Guild->value],
            'release_date' => now()->subDays(3),
        ]);
        $this->attachTags($t7, $characters, $keywords, [6], [5]);

        $t8 = Transmission::create([
            'title' => 'Resurrectionists: A Beginner\'s Guide to the Undead',
            'description' => 'New to Resurrectionists? This guide covers everything from choosing your first master to understanding the summoning mechanics.',
            'url' => 'https://soulstonequarterly.example.com/ressers-guide',
            'channel_id' => $channel3->id,
            'transmission_type' => TransmissionTypeEnum::Website,
            'content_type' => ContentTypeEnum::DeepDives,
            'factions' => [FactionEnum::Resurrectionists->value],
            'release_date' => now()->subDays(10),
        ]);
        $this->attachTags($t8, $characters, $keywords, [7], [6]);

        // --- Transmissions for Channel 4: Bayou Broadcasting Co. ---

        $t9 = Transmission::create([
            'title' => 'Bayou Battle: Gremlins vs Explorer\'s Society',
            'description' => 'The swamp meets the frontier! Watch our latest battle report featuring classic Bayou shenanigans.',
            'url' => 'https://www.youtube.com/watch?v=example9',
            'channel_id' => $channel4->id,
            'transmission_type' => TransmissionTypeEnum::YouTube,
            'content_type' => ContentTypeEnum::BattleReports,
            'factions' => [FactionEnum::Bayou->value, FactionEnum::ExplorersSociety->value],
            'release_date' => now()->subDays(4),
        ]);
        $this->attachTags($t9, $characters, $keywords, [8, 9], [7]);

        $t10 = Transmission::create([
            'title' => 'Swamp Stories: The Origins of the Bayou',
            'description' => 'A lore deep-dive into how the Bayou came to be and the colorful characters who call it home.',
            'url' => 'https://bayoubroadcasting.example.com/swamp-stories',
            'channel_id' => $channel4->id,
            'transmission_type' => TransmissionTypeEnum::Website,
            'content_type' => ContentTypeEnum::Lore,
            'factions' => [FactionEnum::Bayou->value],
            'release_date' => now()->subDays(11),
        ]);
        $this->attachTags($t10, $characters, $keywords, [8], [7]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Character>  $characters
     * @param  \Illuminate\Database\Eloquent\Collection<int, Keyword>  $keywords
     * @param  int[]  $characterIndices
     * @param  int[]  $keywordIndices
     */
    private function attachTags(Transmission $transmission, $characters, $keywords, array $characterIndices, array $keywordIndices): void
    {
        $charIds = collect($characterIndices)
            ->map(fn (int $i) => $characters->get($i)?->id)
            ->filter()
            ->toArray();

        $kwIds = collect($keywordIndices)
            ->map(fn (int $i) => $keywords->get($i)?->id)
            ->filter()
            ->toArray();

        if ($charIds) {
            $transmission->characters()->attach($charIds);
        }
        if ($kwIds) {
            $transmission->keywords()->attach($kwIds);
        }
    }
}
