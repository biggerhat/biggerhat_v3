<?php

namespace Database\Seeders;

use App\Enums\DeploymentEnum;
use App\Enums\FactionEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    /**
     * Seed a test tournament in Active status with a mix of registered users and manual players.
     * Creates 3 planned rounds with the first round fully configured.
     */
    public function run(): void
    {
        // Get or create the tournament organizer (first admin or first user)
        $organizer = User::first();
        if (! $organizer) {
            $organizer = User::factory()->create(['name' => 'Tournament Organizer', 'email' => 'to@biggerhat.test']);
        }

        // Create a few BiggerHat user players
        $userPlayers = collect();
        $existingUsers = User::where('id', '!=', $organizer->id)->take(4)->get();
        foreach ($existingUsers as $user) {
            $userPlayers->push($user);
        }
        // Fill up to 4 user players if needed
        while ($userPlayers->count() < 4) {
            $userPlayers->push(User::factory()->create());
        }

        $factions = collect(FactionEnum::cases());

        // Create the tournament
        $tournament = Tournament::create([
            'name' => 'BiggerHat Weekly #'.rand(1, 99),
            'description' => 'A seeded test tournament for development. Mix of registered users and walk-in players.',
            'creator_id' => $organizer->id,
            'encounter_size' => 50,
            'planned_rounds' => 3,
            'season' => PoolSeasonEnum::GainingGrounds0->value,
            'status' => TournamentStatusEnum::Active,
            'location' => 'The Friendly Local Game Store',
            'event_date' => now()->addDays(rand(1, 14)),
            'round_time_limit' => 135,
        ]);

        $tournament->organizers()->attach($organizer->id);

        $this->command->info("Created tournament: {$tournament->name} ({$tournament->uuid})");

        // Add players — 4 BiggerHat users + 4 manual (non-user) players + 1 ringer = 9 players
        $playerNames = [
            // Manual players (no BiggerHat account)
            'Dave the Casual',
            'New Player Steve',
            'Tournament Regular Mike',
            'Walk-in Player #4',
        ];

        $allPlayers = collect();

        // Add user-linked players
        foreach ($userPlayers as $idx => $user) {
            $player = TournamentPlayer::create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'display_name' => $user->name,
                'faction' => $factions->random()->value,
            ]);
            $allPlayers->push($player);
        }

        // Add manual players (no user account)
        foreach ($playerNames as $name) {
            $player = TournamentPlayer::create([
                'tournament_id' => $tournament->id,
                'user_id' => null,
                'display_name' => $name,
                'faction' => $factions->random()->value,
            ]);
            $allPlayers->push($player);
        }

        // Add a ringer
        $ringer = TournamentPlayer::create([
            'tournament_id' => $tournament->id,
            'user_id' => $organizer->id,
            'display_name' => $organizer->name.' (Ringer)',
            'faction' => $factions->random()->value,
            'is_ringer' => true,
        ]);
        $allPlayers->push($ringer);

        $this->command->info("Added {$allPlayers->count()} players ({$userPlayers->count()} users, ".count($playerNames).' manual, 1 ringer)');

        // Create all 3 rounds
        $strategies = Strategy::forSeason(PoolSeasonEnum::GainingGrounds0)->get();
        $schemes = Scheme::forSeason(PoolSeasonEnum::GainingGrounds0)->get();
        $deployments = DeploymentEnum::cases();

        for ($r = 1; $r <= 3; $r++) {
            $schemePool = $schemes->count() >= 3
                ? $schemes->random(3)->pluck('id')->toArray()
                : [];

            TournamentRound::create([
                'tournament_id' => $tournament->id,
                'round_number' => $r,
                'status' => TournamentRoundStatusEnum::Setup,
                'deployment' => $deployments[array_rand($deployments)]->value,
                'strategy_id' => $strategies->isNotEmpty() ? $strategies->random()->id : null,
                'scheme_pool' => $schemePool,
            ]);
        }

        $this->command->info('Created 3 rounds with random scenarios');
        $this->command->info('');
        $this->command->info('Tournament is ACTIVE and ready for pairing.');
        $this->command->info("Manage URL: /tournaments/{$tournament->uuid}");
        $this->command->info("View URL:   /tournaments/{$tournament->uuid}/view");
    }
}
