<?php

namespace Database\Seeders;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\GameRoleEnum;
use App\Enums\RoleEnum;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Friendship;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Miniature;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Local-only fixture data for browsing logged-in screens (UI audits, screenshots).
 * Builds three users — member, tournament organizer, super admin — each with the
 * password "password", plus crews, a collection, games and a tournament made from
 * whatever catalog data is already in the database (run app:seed-from-prod first).
 * See docs/ui-audit.md.
 */
class UiAuditSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        abort_if(app()->isProduction(), 500, 'UiAuditSeeder must never run in production.');

        $this->call([RoleSeeder::class, PermissionSeeder::class]);
        Role::findByName(RoleEnum::SuperAdmin->value)->syncPermissions(Permission::all());
        Role::findByName(RoleEnum::TournamentOrganizer->value)->syncPermissions(
            Permission::whereIn('name', ['create_tournaments', 'manage_tournaments'])->get(),
        );

        $member = $this->user('Mara Member', 'audit.member@example.test');
        $organizer = $this->user('Orla Organizer', 'audit.organizer@example.test', RoleEnum::TournamentOrganizer);
        $this->user('Ada Admin', 'audit.admin@example.test', RoleEnum::SuperAdmin);
        $opponent = $this->user('Otto Opponent', 'audit.opponent@example.test');
        $pending = $this->user('Pia Pending', 'audit.pending@example.test');

        Friendship::firstOrCreate(['requester_id' => $member->id, 'addressee_id' => $opponent->id], ['accepted_at' => now()]);
        Friendship::firstOrCreate(['requester_id' => $pending->id, 'addressee_id' => $member->id]);

        $this->collection($member);
        $crews = $this->crews($member);
        $this->games($member, $opponent, $crews);
        $this->tournament($organizer, $member, $opponent);
    }

    private function user(string $name, string $email, ?RoleEnum $role = null): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(self::PASSWORD), 'email_verified_at' => now()],
        );
        if ($role) {
            $user->syncRoles([$role->value]);
        }

        return $user;
    }

    private function collection(User $member): void
    {
        $minis = Miniature::query()->inRandomOrder()->limit(24)->get();
        foreach ($minis as $i => $mini) {
            $member->collectionMiniatures()->syncWithoutDetaching([
                $mini->id => ['quantity' => 1 + ($i % 2), 'is_built' => $i % 3 !== 0, 'is_painted' => $i % 4 === 0],
            ]);
        }
        $wishlist = Wishlist::firstOrCreate(['user_id' => $member->id, 'name' => 'Next purchases']);
        $wishlist->refresh();
    }

    /** @return array<int, CrewBuild> */
    private function crews(User $member): array
    {
        $crews = [];
        foreach ([FactionEnum::Arcanists, FactionEnum::Guild, FactionEnum::Neverborn] as $i => $faction) {
            $master = Character::where('faction', $faction)->where('station', CharacterStationEnum::Master)->first();
            if (! $master) {
                continue;
            }
            $ids = Character::where('faction', $faction)->where('station', '!=', CharacterStationEnum::Master)->limit(6)->pluck('id')->all();
            $crews[] = CrewBuild::updateOrCreate(
                ['user_id' => $member->id, 'name' => "{$faction->value} audit crew"],
                ['faction' => $faction, 'master_id' => $master->id, 'encounter_size' => 50, 'crew_data' => $ids, 'is_public' => $i === 0, 'is_archived' => false],
            );
        }

        return $crews;
    }

    /** @param  array<int, CrewBuild>  $crews */
    private function games(User $member, User $opponent, array $crews): void
    {
        if (Game::where('creator_id', $member->id)->exists()) {
            return; // already seeded; re-running would duplicate games
        }

        $strategy = Strategy::first();
        $schemes = Scheme::limit(3)->pluck('id')->all();
        $make = function (string $state, ?int $creator = null) use ($member, $strategy, $schemes) {
            return Game::factory()->{$state}()->create([
                'creator_id' => $creator ?? $member->id,
                'strategy_id' => $strategy?->id,
                'scheme_pool' => $schemes,
                'is_observable' => true,
            ]);
        };

        foreach (['inProgress', 'completed'] as $state) {
            $game = $make($state);
            foreach ([[$member, 1, FactionEnum::Arcanists], [$opponent, 2, FactionEnum::Guild]] as [$user, $slot, $faction]) {
                $player = GamePlayer::factory()->create([
                    'game_id' => $game->id, 'user_id' => $user->id, 'slot' => $slot, 'faction' => $faction,
                    'role' => $slot === 1 ? GameRoleEnum::Attacker : GameRoleEnum::Defender,
                    'current_scheme_id' => $schemes[$slot - 1] ?? null, 'scheme_pool' => $schemes,
                ]);
                Character::where('faction', $faction)->limit(5)->get()->each(function (Character $c, int $n) use ($game, $player, $faction) {
                    GameCrewMember::factory()->create([
                        'game_id' => $game->id, 'game_player_id' => $player->id, 'character_id' => $c->id,
                        'display_name' => $c->display_name, 'faction' => $faction->value, 'cost' => $c->cost ?? 5,
                        'station' => $c->station->value ?? 'minion', 'current_health' => $c->health ?? 6, 'max_health' => $c->health ?? 6, 'sort_order' => $n,
                    ]);
                });
            }
        }
        $make('solo');
    }

    private function tournament(User $organizer, User $member, User $opponent): void
    {
        if (Tournament::where('name', 'Audit Open')->exists()) {
            return;
        }

        $tournament = Tournament::factory()->active()->create(['creator_id' => $organizer->id, 'name' => 'Audit Open', 'planned_rounds' => 3]);
        $players = collect([$member, $opponent])->map(fn (User $u) => TournamentPlayer::factory()->create([
            'tournament_id' => $tournament->id, 'user_id' => $u->id, 'display_name' => $u->name,
        ]));
        $players = $players->merge(TournamentPlayer::factory()->count(6)->create(['tournament_id' => $tournament->id]));
        TournamentRound::factory()->inProgress()->create(['tournament_id' => $tournament->id, 'round_number' => 1]);
        Tournament::factory()->inRegistration()->create(['creator_id' => $organizer->id, 'name' => 'Audit Spring Open']);
        unset($players);
    }
}
