use App\Models\{User, Tournament, TournamentPlayer, TournamentRound, TournamentGame, TournamentRsvp, Strategy, Scheme};
use App\Models\TOS\Allegiance;

$mk = fn ($name, $email) => User::factory()->create(['name' => $name, 'email' => $email, 'password' => bcrypt('password'), 'email_verified_at' => now()]);
$org = $mk('Olivia Organizer', 'org@example.test');
$alice = $mk('Alice', 'alice@example.test');
$bob = $mk('Bob', 'bob@example.test');
$carol = $mk('Carol', 'carol@example.test');
$dave = $mk('Dave', 'dave@example.test');

// A master with a title, so score entry has leaders to pick (the picker lists masters of the player's faction).
\App\Models\Token::factory()->create(['name' => 'Focus']);
\App\Models\Token::factory()->create(['name' => 'Shielded']);
\App\Models\Character::factory()->create(['name' => 'Seedlady Alpha', 'title' => null, 'station' => 'master', 'faction' => 'guild', 'base' => 50, 'size' => 3, 'speed' => 6, 'health' => 9]);
\App\Models\Character::factory()->create(['name' => 'Seedlady Beta', 'title' => null, 'station' => 'minion', 'faction' => 'guild', 'base' => 30, 'size' => 1, 'speed' => 5]);
\App\Models\Character::factory()->create(['name' => 'Rasputina', 'title' => 'Winter Witch', 'station' => 'master', 'faction' => 'arcanists']);
// A small made-up crew for the help screenshots (flows38): neutral names, no card art.
$helpMaster = \App\Models\Character::factory()->create(['name' => 'Marshal Hale', 'title' => null, 'station' => 'master', 'faction' => 'guild', 'base' => 40, 'size' => 2, 'speed' => 6, 'health' => 10, 'cost' => null, 'count' => 1]);
$helpCrew = collect([['Sergeant Pike', 'minion', 7, 40, 8], ['Recruit Finch', 'minion', 4, 30, 5], ['Lookout Wren', 'minion', 5, 30, 5]])
    ->map(fn ($c) => \App\Models\Character::factory()->create(['name' => $c[0], 'title' => null, 'station' => $c[1], 'faction' => 'guild', 'cost' => $c[2], 'base' => $c[3], 'size' => 2, 'speed' => 5, 'health' => $c[4], 'count' => 1]));
// a keyword they share, so the hiring pool shows them as in-keyword (as a real crew would) rather than all out of keyword
$helpKeyword = \App\Models\Keyword::factory()->create(['name' => 'Patrol']);
foreach (collect([$helpMaster])->merge($helpCrew) as $helpCharacter) {
    $helpCharacter->keywords()->attach($helpKeyword->id);
}
$helpBuild = \App\Models\CrewBuild::factory()->create(['user_id' => $carol->id, 'name' => 'Demo Patrol', 'faction' => 'guild', 'encounter_size' => 50, 'master_id' => $helpMaster->id, 'crew_data' => $helpCrew->pluck('id')->all(), 'is_public' => true]);

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
// The Game Tracker's Create form defaults to the newest season; the factories above pick a random one, so make sure that
// season always has a strategy and schemes or a freshly created game gets an empty scheme pool.
$defaultSeason = \App\Enums\PoolSeasonEnum::defaultSeason()->value;
Strategy::factory()->count(2)->create(['season' => $defaultSeason]);
Scheme::factory()->count(4)->create(['season' => $defaultSeason]);
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
foreach ([['Alice', 'arcanists', $alice], ['Bob', 'guild', $bob], ['Carol', 'bayou', $carol], ['Dave', 'neverborn', $dave], ['Eve', 'outcasts', null]] as [$n, $f, $u]) {
    $rp[$n] = TournamentPlayer::factory()->for($t7)->create(['display_name' => $n, 'faction' => $f, 'user_id' => $u?->id]);
}
$q1 = TournamentRound::factory()->for($t7)->create(['round_number' => 1, 'status' => 'completed', 'strategy_id' => $strategy->id]);
TournamentGame::factory()->for($q1, 'round')->withScore(9, 3)->create(['player_one_id' => $rp['Alice']->id, 'player_two_id' => $rp['Bob']->id, 'result' => 'completed', 'table_number' => 1]);
TournamentGame::factory()->for($q1, 'round')->withScore(7, 5)->create(['player_one_id' => $rp['Carol']->id, 'player_two_id' => $rp['Dave']->id, 'result' => 'completed', 'table_number' => 2]);
$q2 = TournamentRound::factory()->for($t7)->create(['round_number' => 2, 'status' => 'completed']);
TournamentGame::factory()->for($q2, 'round')->withScore(8, 4)->create(['player_one_id' => $rp['Alice']->id, 'player_two_id' => $rp['Carol']->id, 'result' => 'completed', 'table_number' => 1]);
TournamentGame::factory()->for($q2, 'round')->withScore(6, 6)->create(['player_one_id' => $rp['Bob']->id, 'player_two_id' => $rp['Dave']->id, 'result' => 'completed', 'table_number' => 2]);
// Analytics: Round 1's first pairing was played in the Game Tracker (one scored scheme, one discarded).
$trackedGame = App\Models\Game::factory()->solo()->create(['creator_id' => $dave->id]);
$trackedSide = App\Models\GamePlayer::factory()->create(['game_id' => $trackedGame->id, 'user_id' => $alice->id, 'slot' => 1]);
$q1->games()->orderBy('id')->first()->update(['game_id' => $trackedGame->id]);
foreach ([[1, 'scored', 2, 0], [2, 'discarded', 0, 1], [3, 'scored', 3, 2]] as [$turn, $action, $points, $i]) {
    App\Models\GameTurn::create(['game_id' => $trackedGame->id, 'game_player_id' => $trackedSide->id, 'turn_number' => $turn, 'scheme_id' => Scheme::find($schemes[$i])->id, 'scheme_action' => $action, 'scheme_points' => $points]);
}
// --- T8: an active event that asks for a crew each round; Round 1 is paired (scenario set) but not started: Alice vs Bob, both guild
$t8 = Tournament::factory()->active()->create(['name' => 'Crew Check Open', 'creator_id' => $carol->id, 'require_crew_lists' => true, 'encounter_size' => 50, 'planned_rounds' => 2]);
$t8->organizers()->attach($org->id);
Strategy::factory()->count(1)->create(['season' => $t8->season->value]);
$c1 = TournamentPlayer::factory()->for($t8)->create(['display_name' => 'Alice', 'user_id' => $alice->id, 'faction' => 'guild']);
$c2 = TournamentPlayer::factory()->for($t8)->create(['display_name' => 'Bob', 'user_id' => $bob->id, 'faction' => 'guild']);
$cr = TournamentRound::factory()->for($t8)->create(['round_number' => 1, 'strategy_id' => Strategy::first()->id, 'deployment' => \App\Enums\DeploymentEnum::cases()[0], 'scheme_pool' => $schemes]);
TournamentGame::factory()->for($cr, 'round')->create(['player_one_id' => $c1->id, 'player_two_id' => $c2->id, 'table_number' => 1, 'player_one_faction' => 'guild', 'player_two_faction' => 'guild']);
App\Models\CrewBuild::factory()->create(['user_id' => $alice->id, 'name' => 'Alice Guild 50', 'faction' => 'guild', 'encounter_size' => 50]);
App\Models\CrewBuild::factory()->create(['user_id' => $alice->id, 'name' => 'Alice Guild 40', 'faction' => 'guild', 'encounter_size' => 40]);
App\Models\CrewBuild::factory()->create(['user_id' => $alice->id, 'name' => 'Alice Arcanists 50', 'faction' => 'arcanists', 'encounter_size' => 50]);
App\Models\CrewBuild::factory()->create(['user_id' => $bob->id, 'name' => 'Bob Guild 50', 'faction' => 'guild', 'encounter_size' => 50]);
// --- T9: Swiss finished, ready for a top cut. T10: a top 4 whose semifinals are played, ready to advance.
// Created by another account: an organizer may own at most 5 unfinished events, and the org account is already at that limit.
$mkField = function (string $name, int $count) use ($org, $dave) {
    $t = Tournament::factory()->active()->create(['name' => $name, 'creator_id' => $dave->id, 'planned_rounds' => 1]);
    $t->organizers()->attach($org->id);
    $players = collect(range(1, $count))->map(fn ($n) => TournamentPlayer::factory()->for($t)->create(['display_name' => "{$name[0]}$n", 'faction' => 'guild']))->all();
    $swiss = TournamentRound::factory()->for($t)->create(['round_number' => 1, 'status' => \App\Enums\TournamentRoundStatusEnum::Completed]);
    for ($i = 0; $i + 1 < $count; $i += 2) {
        TournamentGame::factory()->for($swiss, 'round')->withScore(10 - $i, 2 + $i)->create(['player_one_id' => $players[$i]->id, 'player_two_id' => $players[$i + 1]->id, 'result' => 'completed', 'table_number' => $i / 2 + 1]);
    }

    return $t;
};
$t9 = $mkField('Cutters', 6);
$t10 = $mkField('Brackets', 4);
$semis = app(\App\Services\TournamentCutService::class)->start($t10, 4);
$semis->update(['status' => \App\Enums\TournamentRoundStatusEnum::Completed, 'strategy_id' => $strategy->id, 'deployment' => \App\Enums\DeploymentEnum::cases()[0], 'scheme_pool' => $schemes]);
foreach ($semis->games()->get() as $i => $g) {
    $g->update(['player_one_vp' => $i === 0 ? 3 : 9, 'player_two_vp' => $i === 0 ? 8 : 2, 'result' => 'completed']);
}


// --- T11: a running event with one tracker game being played right now (the "Live now" strip) and one pairing without a tracker yet
$t11 = Tournament::factory()->active()->create(['name' => 'Live Cup', 'creator_id' => $dave->id, 'planned_rounds' => 2]);
$t11->organizers()->attach($org->id);
$lp = [];
foreach ([[$alice, 'arcanists'], [$bob, 'guild'], [$carol, 'bayou'], [$dave, 'neverborn']] as [$u, $f]) {
    $lp[$u->name] = TournamentPlayer::factory()->for($t11)->create(['display_name' => $u->name, 'user_id' => $u->id, 'faction' => $f]);
}
$r11 = TournamentRound::factory()->for($t11)->inProgress()->create(['round_number' => 1, 'strategy_id' => $strategy->id, 'deployment' => \App\Enums\DeploymentEnum::cases()[0], 'scheme_pool' => $schemes, 'started_at' => now()->subMinutes(10)]);
$liveTracker = App\Models\Game::factory()->inProgress()->create(['creator_id' => $alice->id, 'strategy_id' => $strategy->id, 'current_turn' => 2, 'is_observable' => true]);
TournamentGame::factory()->for($r11, 'round')->create(['player_one_id' => $lp['Alice']->id, 'player_two_id' => $lp['Bob']->id, 'table_number' => 1, 'game_id' => $liveTracker->id]);
TournamentGame::factory()->for($r11, 'round')->create(['player_one_id' => $lp['Carol']->id, 'player_two_id' => $lp['Dave']->id, 'table_number' => 2]);

// --- A standalone solo game waiting at crew select for Dave (Game Tracker -> Crew Builder -> back, with the crew attached)
$crewGame = App\Models\Game::factory()->solo()->create(['creator_id' => $dave->id, 'status' => App\Enums\GameStatusEnum::CrewSelect, 'strategy_id' => $strategy->id]);
App\Models\GamePlayer::factory()->create(['game_id' => $crewGame->id, 'user_id' => $dave->id, 'slot' => 1, 'faction' => 'guild', 'role' => App\Enums\GameRoleEnum::Attacker]);
App\Models\GamePlayer::factory()->soloOpponent()->create(['game_id' => $crewGame->id, 'faction' => 'arcanists', 'role' => App\Enums\GameRoleEnum::Defender]);
$crewBuild = App\Models\CrewBuild::factory()->create(['user_id' => $dave->id, 'name' => 'Dave Tracker Crew', 'faction' => 'guild', 'encounter_size' => 50]);

// --- A solo game already in play with full crews on both sides (play-screen review / flows)
$playGame = App\Models\Game::factory()->solo()->inProgress()->create(['creator_id' => $carol->id, 'strategy_id' => $strategy->id, 'current_turn' => 2, 'is_observable' => true, 'status' => App\Enums\GameStatusEnum::InProgress]);
$pp1 = App\Models\GamePlayer::factory()->create(['game_id' => $playGame->id, 'user_id' => $carol->id, 'slot' => 1, 'faction' => 'bayou', 'role' => App\Enums\GameRoleEnum::Attacker, 'master_name' => 'Pandora', 'soulstone_pool' => 5]);
$pp2 = App\Models\GamePlayer::factory()->soloOpponent()->create(['game_id' => $playGame->id, 'faction' => 'guild', 'role' => App\Enums\GameRoleEnum::Defender, 'master_name' => 'Lady Justice', 'soulstone_pool' => 4]);
foreach ([[$pp1, 'bayou', [['Pandora', 'master', 'leader', 0, 10], ['Silurid', 'minion', 'in-keyword', 6, 6], ['Swampfiend', 'minion', 'in-keyword', 5, 7], ['Bayou Gremlin', 'minion', 'in-keyword', 4, 3], ['Bayou Gremlin', 'minion', 'in-keyword', 4, 3], ['Mad Gremlin', 'minion', 'in-keyword', 8, 9]]], [$pp2, 'guild', [['Lady Justice', 'master', 'leader', 0, 10], ['Guild Guard', 'minion', 'in-keyword', 5, 7], ['Guild Guard', 'minion', 'in-keyword', 5, 7], ['Guild Hound', 'minion', 'in-keyword', 4, 5], ['Judge', 'minion', 'in-keyword', 9, 9]]]] as [$pl, $fac, $rows]) {
    foreach ($rows as $i => [$nm, $st, $cat, $cost, $hp]) {
        App\Models\GameCrewMember::factory()->create(['game_id' => $playGame->id, 'game_player_id' => $pl->id, 'character_id' => null, 'display_name' => $nm, 'faction' => $fac, 'station' => $st, 'hiring_category' => $cat, 'cost' => $cost, 'current_health' => $hp, 'max_health' => $hp, 'sort_order' => $i, 'front_image' => $i === 0 ? 'seed/card-front.png' : null]);
    }
}

// the table as it stood at the end of turn 1, so the board's look back has something to show
$playGame->update(['board_state' => ['history' => ['1' => ['positions' => (object) [], 'markers' => []]]]]);

// character upgrades a player can attach from the play screen or the board
foreach (['Iron Will', 'Sharp Eyes'] as $upgradeName) {
    App\Models\Upgrade::factory()->create(['name' => $upgradeName, 'domain' => App\Enums\UpgradeDomainTypeEnum::Character, 'game_mode_type' => App\Enums\GameModeTypeEnum::Standard, 'type' => null, 'plentiful' => null]);
}

// --- A solo game with a summoned model nobody has placed on the table yet (board flow)
$summonGame = App\Models\Game::factory()->solo()->inProgress()->create(['creator_id' => $carol->id, 'strategy_id' => $strategy->id, 'current_turn' => 1, 'is_observable' => false, 'status' => App\Enums\GameStatusEnum::InProgress]);
$sp1 = App\Models\GamePlayer::factory()->create(['game_id' => $summonGame->id, 'user_id' => $carol->id, 'slot' => 1, 'faction' => 'bayou', 'role' => App\Enums\GameRoleEnum::Attacker, 'master_name' => 'Pandora', 'soulstone_pool' => 5]);
$sp2 = App\Models\GamePlayer::factory()->soloOpponent()->create(['game_id' => $summonGame->id, 'faction' => 'guild', 'role' => App\Enums\GameRoleEnum::Defender, 'master_name' => 'Lady Justice', 'soulstone_pool' => 4]);
foreach ([[$sp1, 'bayou', 'Pandora', false], [$sp1, 'bayou', 'Summoned Imp', true], [$sp2, 'guild', 'Lady Justice', false]] as $i => [$pl, $fac, $nm, $summoned]) {
    App\Models\GameCrewMember::factory()->create(['game_id' => $summonGame->id, 'game_player_id' => $pl->id, 'character_id' => null, 'display_name' => $nm, 'faction' => $fac, 'station' => 'minion', 'hiring_category' => 'in-keyword', 'cost' => 4, 'current_health' => 6, 'max_health' => 6, 'sort_order' => $i, 'is_summoned' => $summoned]);
}

\Laravel\Pennant\Feature::for(null)->activate('experiment-tabletop');
foreach ([['Scheme Marker', 30], ['Strategy Marker', 30], ['Blast Marker', 50], ['Corpse Marker', 40], ['Scrap Marker', 30], ['Marker of the Dead', 30], ['Remains', 30]] as [$markerName, $markerBase]) {
    App\Models\Marker::factory()->create(['name' => $markerName, 'base' => $markerBase, 'is_strategy' => $markerName === 'Strategy Marker']);
}

// --- A two-player tabletop game at the Deployment step: Carol attacks (slot 1), Dave defends (slot 2, three models)
$deployGame = App\Models\Game::factory()->create(['creator_id' => $carol->id, 'is_solo' => false, 'strategy_id' => $strategy->id, 'status' => App\Enums\GameStatusEnum::Deployment, 'play_mode' => App\Enums\GamePlayModeEnum::Tabletop, 'deployment' => 'standard', 'is_observable' => false]);
$dp1 = App\Models\GamePlayer::factory()->create(['game_id' => $deployGame->id, 'user_id' => $carol->id, 'slot' => 1, 'faction' => 'bayou', 'role' => App\Enums\GameRoleEnum::Attacker, 'master_name' => 'Pandora', 'soulstone_pool' => 5]);
$dp2 = App\Models\GamePlayer::factory()->create(['game_id' => $deployGame->id, 'user_id' => $dave->id, 'slot' => 2, 'faction' => 'guild', 'role' => App\Enums\GameRoleEnum::Defender, 'master_name' => 'Lady Justice', 'soulstone_pool' => 4]);
foreach ([[$dp1, 'bayou', ['Pandora', 'Silurid']], [$dp2, 'guild', ['Lady Justice', 'Guild Guard', 'Guild Hound']]] as [$pl, $fac, $names]) {
    foreach ($names as $i => $nm) {
        App\Models\GameCrewMember::factory()->create(['game_id' => $deployGame->id, 'game_player_id' => $pl->id, 'character_id' => null, 'display_name' => $nm, 'faction' => $fac, 'station' => $i === 0 ? 'master' : 'minion', 'hiring_category' => $i === 0 ? 'leader' : 'in-keyword', 'cost' => $i === 0 ? 0 : 5, 'current_health' => 6, 'max_health' => 6, 'sort_order' => $i]);
    }
}

echo "seeded: T1={$t1->uuid} T2={$t2->uuid} T3={$t3->uuid}\n";
file_put_contents(getenv('SEEDOUT'), json_encode(['t1' => $t1->uuid, 't2' => $t2->uuid, 't3' => $t3->uuid, 't4' => $t4->uuid, 't5' => $t5->uuid, 't6' => $t6->uuid, 't7' => $t7->uuid, 't8' => $t8->uuid, 't9' => $t9->uuid, 't10' => $t10->uuid, 't11' => $t11->uuid, 'crew_game' => $crewGame->uuid, 'play_game' => $playGame->uuid, 'deploy_game' => $deployGame->uuid, 'summon_game' => $summonGame->uuid, 'crew_build' => $crewBuild->share_code, 'help_build' => $helpBuild->id, 'g1' => $r1->games()->first()->id]));
