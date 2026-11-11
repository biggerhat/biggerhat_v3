use App\Models\{User, Tournament, TournamentPlayer, TournamentRound, TournamentGame, TournamentRsvp, Strategy, Scheme};
use App\Models\TOS\Allegiance;

$mk = fn ($name, $email) => User::factory()->create(['name' => $name, 'email' => $email, 'password' => bcrypt('password'), 'email_verified_at' => now()]);
$org = $mk('Olivia Organizer', 'org@example.test');
$alice = $mk('Alice', 'alice@example.test');
$bob = $mk('Bob', 'bob@example.test');
$carol = $mk('Carol', 'carol@example.test');
$dave = $mk('Dave', 'dave@example.test');

// --- T1: running Gaining Grounds event with self-reporting
$strategy = Strategy::factory()->create();
$schemes = Scheme::factory()->count(3)->create()->pluck('id')->all();
$t1 = Tournament::factory()->active()->create(['name' => 'GG Open', 'creator_id' => $org->id, 'allow_self_report' => true, 'planned_rounds' => 2]);
$t1->organizers()->attach($org->id);
$pl = [];
foreach ([[$alice, 'arcanists'], [$bob, 'guild'], [$carol, 'bayou'], [$dave, 'neverborn']] as [$u, $f]) {
    $pl[$u->name] = TournamentPlayer::factory()->for($t1)->create(['display_name' => $u->name, 'user_id' => $u->id, 'faction' => $f]);
}
$r1 = TournamentRound::factory()->for($t1)->inProgress()->create(['round_number' => 1, 'strategy_id' => $strategy->id, 'deployment' => \App\Enums\DeploymentEnum::cases()[0], 'scheme_pool' => $schemes, 'started_at' => now()->subMinutes(20)]);
TournamentGame::factory()->for($r1, 'round')->create(['player_one_id' => $pl['Alice']->id, 'player_two_id' => $pl['Bob']->id, 'table_number' => 1]);
TournamentGame::factory()->for($r1, 'round')->create(['player_one_id' => $pl['Carol']->id, 'player_two_id' => $pl['Dave']->id, 'table_number' => 2]);

// --- T2: team event, registration open, four RSVPs
$t2 = Tournament::factory()->inRegistration()->create(['name' => 'Team Cup', 'creator_id' => $org->id, 'team_size' => 2]);
$t2->organizers()->attach($org->id);
foreach ([$alice, $bob, $carol, $dave] as $u) { TournamentRsvp::create(['tournament_id' => $t2->id, 'user_id' => $u->id]); }

// --- T3: Fields of Glory event in registration
Allegiance::factory()->count(2)->create();
(new Database\Seeders\TOS\OperationSeeder)->run();
(new Database\Seeders\TOS\DeploymentSeeder)->run();
$t3 = Tournament::factory()->inRegistration()->create(['name' => 'FoG Open', 'creator_id' => $org->id, 'ruleset' => 'tos_fields_of_glory']);
$t3->organizers()->attach($org->id);

TournamentRound::factory()->for($t3)->create(['round_number' => 1]);
TournamentRound::factory()->for($t3)->create(['round_number' => 2]);
// --- T4: running TEAM event with self-reporting; Alice+Bob vs Carol+Dave (teams of 2)
$t4 = Tournament::factory()->active()->create(['name' => 'Team Active', 'creator_id' => $org->id, 'team_size' => 2, 'allow_self_report' => true]);
$t4->organizers()->attach($org->id);
$mkTeam = function ($name, $users) use ($t4) {
    $team = TournamentPlayer::factory()->for($t4)->create(['display_name' => $name, 'faction' => null]);
    foreach ($users as $i => [$u, $f]) { $team->members()->create(['display_name' => $u->name, 'user_id' => $u->id, 'faction' => $f, 'position' => $i]); }
    return $team;
};
$tA = $mkTeam('Alpha Squad', [[$alice, 'arcanists'], [$bob, 'guild']]);
$tB = $mkTeam('Bravo Crew', [[$carol, 'bayou'], [$dave, 'neverborn']]);
$r4 = TournamentRound::factory()->for($t4)->inProgress()->create(['round_number' => 1, 'started_at' => now()->subMinutes(5)]);
TournamentGame::factory()->for($r4, 'round')->create(['player_one_id' => $tA->id, 'player_two_id' => $tB->id, 'table_number' => 1]);
echo "seeded: T1={$t1->uuid} T2={$t2->uuid} T3={$t3->uuid}\n";
file_put_contents(getenv('SEEDOUT'), json_encode(['t1' => $t1->uuid, 't2' => $t2->uuid, 't3' => $t3->uuid, 't4' => $t4->uuid, 'g1' => $r1->games()->first()->id]));
