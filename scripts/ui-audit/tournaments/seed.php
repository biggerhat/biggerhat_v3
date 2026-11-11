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
// --- T5 (created by Carol so the organizer stays under the 5-unfinished-events cap): active event, round 1 in setup with two pairings and one unpaired player (for the swap flows)
$t5 = Tournament::factory()->active()->create(['name' => 'Swap Cup', 'creator_id' => $carol->id, 'planned_rounds' => 2]);
$t5->organizers()->attach($org->id);
// scenario pickers list only the event's own season, so give it a few strategies and schemes to pick from
Strategy::factory()->count(2)->create(['season' => $t5->season->value]);
Scheme::factory()->count(4)->create(['season' => $t5->season->value]);
$sp = [];
foreach (['Ann', 'Ben', 'Cat', 'Dan', 'Eve'] as $i => $n) {
    $sp[$n] = TournamentPlayer::factory()->for($t5)->create(['display_name' => $n, 'faction' => ['arcanists', 'guild', 'bayou', 'neverborn', 'outcasts'][$i]]);
}
$r5 = TournamentRound::factory()->for($t5)->create(['round_number' => 1]);
TournamentGame::factory()->for($r5, 'round')->create(['player_one_id' => $sp['Ann']->id, 'player_two_id' => $sp['Ben']->id, 'table_number' => 1]);
TournamentGame::factory()->for($r5, 'round')->create(['player_one_id' => $sp['Cat']->id, 'player_two_id' => $sp['Dan']->id, 'table_number' => 2]);
// --- T6: registration open, two seats, both taken (Alice and Zed), Bob RSVPed (waitlist #1), Carol RSVPed (waitlist #2)
$t6 = Tournament::factory()->inRegistration()->create(['name' => 'Capped Open', 'creator_id' => $dave->id, 'max_players' => 2]);
$t6->organizers()->attach($org->id);
TournamentPlayer::factory()->for($t6)->create(['display_name' => 'Alice', 'user_id' => $alice->id, 'faction' => 'arcanists']);
TournamentPlayer::factory()->for($t6)->create(['display_name' => 'Zed', 'faction' => 'guild']);
foreach ([[$alice, 0], [$bob, 1], [$carol, 2]] as [$u, $min]) {
    TournamentRsvp::create(['tournament_id' => $t6->id, 'user_id' => $u->id, 'created_at' => now()->addMinutes($min)]);
}
// --- T7: a finished event with results (podium, PDF, shareable image)
$t7 = Tournament::factory()->create(['name' => 'Spring Showdown', 'status' => 'completed', 'creator_id' => $dave->id, 'planned_rounds' => 2]);
$t7->organizers()->attach($org->id);
$rp = [];
foreach ([['Alice', 'arcanists'], ['Bob', 'guild'], ['Carol', 'bayou'], ['Dave', 'neverborn'], ['Eve', 'outcasts']] as [$n, $f]) {
    $rp[$n] = TournamentPlayer::factory()->for($t7)->create(['display_name' => $n, 'faction' => $f]);
}
$q1 = TournamentRound::factory()->for($t7)->create(['round_number' => 1, 'status' => 'completed']);
TournamentGame::factory()->for($q1, 'round')->withScore(9, 3)->create(['player_one_id' => $rp['Alice']->id, 'player_two_id' => $rp['Bob']->id, 'result' => 'completed', 'table_number' => 1]);
TournamentGame::factory()->for($q1, 'round')->withScore(7, 5)->create(['player_one_id' => $rp['Carol']->id, 'player_two_id' => $rp['Dave']->id, 'result' => 'completed', 'table_number' => 2]);
$q2 = TournamentRound::factory()->for($t7)->create(['round_number' => 2, 'status' => 'completed']);
TournamentGame::factory()->for($q2, 'round')->withScore(8, 4)->create(['player_one_id' => $rp['Alice']->id, 'player_two_id' => $rp['Carol']->id, 'result' => 'completed', 'table_number' => 1]);
TournamentGame::factory()->for($q2, 'round')->withScore(6, 6)->create(['player_one_id' => $rp['Bob']->id, 'player_two_id' => $rp['Dave']->id, 'result' => 'completed', 'table_number' => 2]);
// --- T8: registration open and crew lists required; Alice (no faction yet) and Bob (guild) are registered, each with saved crews
$t8 = Tournament::factory()->inRegistration()->create(['name' => 'Crew Check Open', 'creator_id' => $carol->id, 'require_crew_lists' => true, 'encounter_size' => 50]);
$t8->organizers()->attach($org->id);
TournamentPlayer::factory()->for($t8)->create(['display_name' => 'Alice', 'user_id' => $alice->id, 'faction' => null]);
TournamentPlayer::factory()->for($t8)->create(['display_name' => 'Bob', 'user_id' => $bob->id, 'faction' => 'guild']);
App\Models\CrewBuild::factory()->create(['user_id' => $alice->id, 'name' => 'Alice Guild 50', 'faction' => 'guild', 'encounter_size' => 50]);
App\Models\CrewBuild::factory()->create(['user_id' => $alice->id, 'name' => 'Alice Arcanists 40', 'faction' => 'arcanists', 'encounter_size' => 40]);
App\Models\CrewBuild::factory()->create(['user_id' => $bob->id, 'name' => 'Bob Guild 50', 'faction' => 'guild', 'encounter_size' => 50]);
echo "seeded: T1={$t1->uuid} T2={$t2->uuid} T3={$t3->uuid}\n";
file_put_contents(getenv('SEEDOUT'), json_encode(['t1' => $t1->uuid, 't2' => $t2->uuid, 't3' => $t3->uuid, 't4' => $t4->uuid, 't5' => $t5->uuid, 't6' => $t6->uuid, 't7' => $t7->uuid, 't8' => $t8->uuid, 'g1' => $r1->games()->first()->id]));
