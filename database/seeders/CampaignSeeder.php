<?php

namespace Database\Seeders;

use App\Enums\BaseSizeEnum;
use App\Enums\Campaign\CampaignPlayerRoleEnum;
use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\Campaign\LeaderArchetypeEnum;
use App\Enums\FactionEnum;
use App\Enums\GameFormatEnum;
use App\Enums\GameRoleEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\PoolSeasonEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Character;
use App\Models\CustomCharacter;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Keyword;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * Seeds a fully clickable campaign state for local dev testing.
 *
 * Creates two users, an active campaign, built leaders, stocked arsenals, and
 * a live campaign game already at MasterSelect so the game tracker is
 * immediately testable.
 *
 * Run with:  php artisan db:seed --class=CampaignSeeder
 *
 * Idempotent on users (first-or-create by email). Re-running adds a second
 * campaign — useful for testing the campaign hub list.
 */
class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensurePermissions();

        $userA = $this->ensureUser('alpha@biggerhat.test', 'Alpha Player');
        $userB = $this->ensureUser('beta@biggerhat.test', 'Beta Player');

        foreach ([$userA, $userB] as $user) {
            $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);
            $user->givePermissionTo(PermissionEnum::ViewCampaignCatalog->value);
        }

        [$kwA1, $kwA2, $kwB1, $kwB2] = $this->pickKeywords();

        $campaign = Campaign::create([
            'name' => 'The Ashes of Malifaux',
            'organizer_user_id' => $userA->id,
            'status' => CampaignStatusEnum::Active->value,
            'started_at' => now(),
            'length_weeks' => 8,
            'current_week' => 2,
            'is_solo' => false,
            'competitive' => false,
            'weekly_event_active' => false,
            'optional_rules' => [],
        ]);

        CampaignPlayer::create(['campaign_id' => $campaign->id, 'user_id' => $userA->id, 'role' => CampaignPlayerRoleEnum::Organizer->value]);
        CampaignPlayer::create(['campaign_id' => $campaign->id, 'user_id' => $userB->id, 'role' => CampaignPlayerRoleEnum::Player->value]);

        $crewA = CampaignCrew::create([
            'campaign_id' => $campaign->id,
            'user_id' => $userA->id,
            'name' => "Alpha's Crew",
            'faction' => FactionEnum::Arcanists->value,
            'keyword_1_id' => $kwA1,
            'keyword_2_id' => $kwA2,
            'scrip' => 10,
        ]);
        $crewB = CampaignCrew::create([
            'campaign_id' => $campaign->id,
            'user_id' => $userB->id,
            'name' => "Beta's Crew",
            'faction' => FactionEnum::Guild->value,
            'keyword_1_id' => $kwB1,
            'keyword_2_id' => $kwB2,
            'scrip' => 5,
        ]);

        $leaderA = $this->buildLeader($userA, $crewA, 'Sera Ashwood', FactionEnum::Arcanists, LeaderArchetypeEnum::TalentedIndividual);
        $leaderB = $this->buildLeader($userB, $crewB, 'Victor Hale', FactionEnum::Guild, LeaderArchetypeEnum::HeavyHitter);

        $this->seedArsenal($crewA, FactionEnum::Arcanists);
        $this->seedArsenal($crewB, FactionEnum::Guild);

        $game = $this->createLiveGame($campaign, $crewA, $userA, $crewB, $userB);

        $this->command?->info('');
        $this->command?->info("Campaign seeded: {$campaign->name} (ID {$campaign->id})");
        $this->command?->info('');
        $this->command?->info('  User A (organizer)  alpha@biggerhat.test  /  password');
        $this->command?->info("    Crew: {$crewA->name}  |  Arcanists  |  Leader: {$leaderA->name}");
        $this->command?->info('');
        $this->command?->info('  User B (player)     beta@biggerhat.test   /  password');
        $this->command?->info("    Crew: {$crewB->name}  |  Guild  |  Leader: {$leaderB->name}");
        $this->command?->info('');
        $this->command?->info("  Live game (MasterSelect):  /games/{$game->uuid}");
        $this->command?->info("  Campaign hub (as alpha):   /campaigns/{$campaign->id}");
    }

    private function ensurePermissions(): void
    {
        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value]);
        }
    }

    private function ensureUser(string $email, string $name): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }

    /**
     * Return 4 keyword IDs [kwA1, kwA2, kwB1, kwB2].
     * Uses real keywords if the prod seed has run; falls back to factory-created
     * placeholder keywords so the seeder works on a fresh install.
     */
    private function pickKeywords(): array
    {
        $ids = Keyword::inRandomOrder()->take(4)->pluck('id')->all();
        while (count($ids) < 4) {
            $ids[] = Keyword::factory()->create(['name' => 'Test Keyword '.count($ids)])->id;
        }

        return $ids;
    }

    private function buildLeader(User $user, CampaignCrew $crew, string $name, FactionEnum $faction, LeaderArchetypeEnum $archetype): CustomCharacter
    {
        return CustomCharacter::create([
            'user_id' => $user->id,
            'campaign_crew_id' => $crew->id,
            'is_campaign_leader' => true,
            'is_campaign_totem' => false,
            'current' => true,
            'archetype' => $archetype->value,
            'campaign_size' => BaseSizeEnum::ThirtyMM->value,
            'campaign_df' => $archetype->df(),
            'campaign_wp' => $archetype->wp(),
            'campaign_sp' => $archetype->sp(),
            'campaign_health' => $archetype->health(),
            'name' => $name,
            'display_name' => $name,
            'faction' => $faction->value,
            'station' => 'master',
            'health' => $archetype->health(),
            'defense' => $archetype->df(),
            'willpower' => $archetype->wp(),
            'speed' => $archetype->sp(),
            'size' => BaseSizeEnum::ThirtyMM->value,
            'base' => BaseSizeEnum::ThirtyMM->value,
            'cost' => null,
            'generates_stone' => true,
            'is_unhirable' => false,
            'actions' => [],
            'abilities' => [],
            'keywords' => [],
            'characteristics' => [],
        ]);
    }

    /**
     * Seed 5 arsenal models for a crew using factory-created characters.
     * Using Character::factory() keeps the seeder independent of the prod seed.
     * Stats (station, cost) are chosen to produce a realistic ~35ss arsenal.
     */
    private function seedArsenal(CampaignCrew $crew, FactionEnum $faction): void
    {
        $models = [
            ['station' => 'minion', 'cost' => 8, 'is_peon' => false],
            ['station' => 'minion', 'cost' => 7, 'is_peon' => false],
            ['station' => 'minion', 'cost' => 6, 'is_peon' => false],
            ['station' => 'minion', 'cost' => 5, 'is_peon' => false],
            ['station' => 'peon',   'cost' => 4, 'is_peon' => true],
        ];

        foreach ($models as $i => $m) {
            $char = Character::factory()->create([
                'faction' => $faction->value,
                'station' => $m['station'],
                'cost' => $m['cost'],
                'name' => ucfirst($faction->value).' Seeded '.ucfirst($m['station']).' '.($i + 1),
                'title' => null,
            ]);

            CampaignArsenalModel::create([
                'campaign_crew_id' => $crew->id,
                'character_id' => $char->id,
                'is_peon' => $m['is_peon'],
                'acquired_week' => 1,
                'acquired_via' => 'hire',
            ]);
        }
    }

    private function createLiveGame(Campaign $campaign, CampaignCrew $crewA, User $userA, CampaignCrew $crewB, User $userB): Game
    {
        $game = Game::create([
            'format' => GameFormatEnum::Campaign->value,
            'status' => GameStatusEnum::MasterSelect->value,
            'season' => PoolSeasonEnum::cases()[0]->value,
            'creator_id' => $userA->id,
            'encounter_size' => 50,
            'started_at' => now(),
            'is_solo' => false,
        ]);

        $roles = collect([GameRoleEnum::Attacker->value, GameRoleEnum::Defender->value])->shuffle();

        GamePlayer::create(['game_id' => $game->id, 'user_id' => $userA->id, 'slot' => 1, 'role' => $roles[0], 'faction' => FactionEnum::Arcanists->value]);
        GamePlayer::create(['game_id' => $game->id, 'user_id' => $userB->id, 'slot' => 2, 'role' => $roles[1], 'faction' => FactionEnum::Guild->value]);

        CampaignGame::create([
            'campaign_id' => $campaign->id,
            'week_number' => $campaign->current_week,
            'crew_a_id' => $crewA->id,
            'crew_b_id' => $crewB->id,
            'base_game_id' => $game->id,
            'encounter_size' => 50,
            'cr_a' => 0,
            'cr_b' => 0,
            'ss_bonus_to_lower' => 0,
            'status' => 'setup',
        ]);

        return $game;
    }
}
