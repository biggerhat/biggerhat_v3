<?php

use App\Enums\FactionEnum;
use App\Enums\TournamentGameResultEnum;
use App\Models\Game;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Garrison;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentPlayerMember;
use App\Models\TournamentRound;
use App\Models\User;
use App\Notifications\Tournament\TournamentRoundPaired;
use App\Services\TournamentStandingsService;
use App\Services\TournamentStateMachine;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->t = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id, 'team_size' => 2]);
    $this->member = fn (array $extra = []) => ['display_name' => fake()->name(), 'faction' => FactionEnum::Arcanists->value, ...$extra];
    $this->addTeam = fn (array $payload, ?Tournament $t = null) => $this->actingAs($this->owner)
        ->postJson(route('tournaments.players.add', $t ?? $this->t), $payload);
    $this->team = function (string $name = 'Team', array $users = [], ?Tournament $t = null) {
        $t ??= $this->t;
        $team = TournamentPlayer::factory()->for($t)->create(['display_name' => $name, 'faction' => null]);
        foreach ($users as $i => $user) {
            TournamentPlayerMember::factory()->create(['tournament_player_id' => $team->id, 'user_id' => $user?->id, 'display_name' => "{$name} {$i}", 'position' => $i]);
        }

        return $team;
    };
});

describe('creating team events', function () {
    it('creates an event with a team size and carries it when copying', function () {
        $this->actingAs($this->owner)->post(route('tournaments.store'), [
            'name' => 'Team Cup', 'encounter_size' => 50, 'planned_rounds' => 3, 'team_size' => 2,
            'season' => Tournament::factory()->make()->season->value, 'event_date' => now()->addWeek()->toDateString(),
        ])->assertRedirect();

        $created = Tournament::latest('id')->first();
        expect($created->team_size)->toBe(2)->and($created->isTeamEvent())->toBeTrue();

        $this->actingAs($this->owner)->get(route('tournaments.create', ['copy' => $created->uuid]))
            ->assertInertia(fn (Assert $page) => $page->where('template.team_size', 2));
    });

    it('rejects team sizes outside 1–4', function () {
        $this->actingAs($this->owner)->postJson(route('tournaments.store'), [
            'name' => 'Too Big', 'encounter_size' => 50, 'planned_rounds' => 3, 'team_size' => 5,
            'season' => Tournament::factory()->make()->season->value, 'event_date' => now()->addWeek()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('team_size');
    });

    it('does not let the team size change once players exist', function () {
        ($this->team)('Alpha');

        $this->actingAs($this->owner)->putJson(route('tournaments.update', $this->t), ['team_size' => 1])
            ->assertStatus(422)->assertJsonFragment(['error' => 'Remove the existing players before switching between individual and team play.']);
        expect($this->t->fresh()->team_size)->toBe(2);
    });
});

describe('adding and editing teams', function () {
    it('adds a team with its members in seat order', function () {
        ($this->addTeam)(['display_name' => 'The Duo', 'members' => [($this->member)(['display_name' => 'Ann']), ($this->member)(['display_name' => 'Bob', 'faction' => FactionEnum::Guild->value])]])
            ->assertOk();

        $team = TournamentPlayer::where('display_name', 'The Duo')->first();
        expect($team->members->pluck('display_name')->all())->toBe(['Ann', 'Bob'])
            ->and($team->members->pluck('position')->all())->toBe([0, 1])
            ->and($team->members[1]->faction)->toBe(FactionEnum::Guild);
    });

    it('requires exactly team-size members, each with a name and a declaration', function () {
        ($this->addTeam)(['display_name' => 'Solo', 'members' => [($this->member)()]])
            ->assertStatus(422)->assertJsonValidationErrors('members');

        ($this->addTeam)(['display_name' => 'No Faction', 'members' => [($this->member)(), ['display_name' => 'Ghost']]])
            ->assertStatus(422)->assertJsonValidationErrors('members.1.faction');

        ($this->addTeam)(['display_name' => 'Missing', 'members' => [['faction' => 'arcanists'], ($this->member)()]])
            ->assertStatus(422)->assertJsonValidationErrors('members.0.display_name');

        expect(TournamentPlayer::count())->toBe(0);
    });

    it('rejects members on an individual event', function () {
        $solo = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id]);

        ($this->addTeam)(['display_name' => 'Nope', 'faction' => 'arcanists', 'members' => [($this->member)()]], $solo)
            ->assertStatus(422)->assertJsonValidationErrors('members');
    });

    it('lets a ringer team have no members', function () {
        ($this->addTeam)(['display_name' => 'Ringer Squad', 'is_ringer' => true])->assertOk();

        expect(TournamentPlayer::where('is_ringer', true)->first()->members)->toHaveCount(0);
    });

    it('stops one account playing twice, on two teams or as a player and a member', function () {
        $user = User::factory()->create();
        ($this->addTeam)(['display_name' => 'One', 'members' => [($this->member)(['user_id' => $user->id]), ($this->member)()]])->assertOk();

        ($this->addTeam)(['display_name' => 'Two', 'members' => [($this->member)(['user_id' => $user->id]), ($this->member)()]])
            ->assertStatus(422)->assertJsonFragment(['error' => 'That user is already linked to another player in this tournament']);
        expect(TournamentPlayer::count())->toBe(1)->and(TournamentPlayerMember::count())->toBe(2);
    });

    it('rejects the same account twice in one team', function () {
        $user = User::factory()->create();

        ($this->addTeam)(['display_name' => 'Twins', 'members' => [($this->member)(['user_id' => $user->id]), ($this->member)(['user_id' => $user->id])]])
            ->assertStatus(422);
    });

    it('edits members in place by id, replacing and removing the rest', function () {
        $team = ($this->team)('Edit Me', [null, null]);
        [$keep, $drop] = $team->members->all();

        $this->actingAs($this->owner)->putJson(route('tournaments.players.update', [$this->t, $team]), [
            'members' => [
                ['id' => $keep->id, 'display_name' => 'Renamed', 'faction' => FactionEnum::Bayou->value],
                ['display_name' => 'Fresh', 'faction' => FactionEnum::Neverborn->value],
            ],
        ])->assertOk();

        $members = $team->fresh()->members;
        expect($members->pluck('display_name')->all())->toBe(['Renamed', 'Fresh'])
            ->and($members[0]->id)->toBe($keep->id)
            ->and(TournamentPlayerMember::find($drop->id))->toBeNull();
    });

    it('rejects editing a member that is not on this team', function () {
        $team = ($this->team)('Mine', [null, null]);
        $other = ($this->team)('Theirs', [null, null]);

        $this->actingAs($this->owner)->putJson(route('tournaments.players.update', [$this->t, $team]), [
            'members' => [['id' => $other->members[0]->id, 'display_name' => 'Hijack', 'faction' => 'guild'], ['display_name' => 'x', 'faction' => 'guild']],
        ])->assertStatus(422);
        expect($other->members[0]->fresh()->display_name)->not->toBe('Hijack');
    });

    it('rolls the team back when a member fails (private garrison of a stranger)', function () {
        $fog = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id, 'ruleset' => 'tos_fields_of_glory', 'team_size' => 2]);
        $allegiance = Allegiance::factory()->create();
        $private = Garrison::factory()->forAllegiance($allegiance)->create(['is_public' => false]);

        ($this->addTeam)(['display_name' => 'Rollback', 'members' => [
            ['display_name' => 'A', 'allegiance_id' => $allegiance->id],
            ['display_name' => 'B', 'allegiance_id' => $allegiance->id, 'garrison_share_code' => $private->share_code],
        ]], $fog)->assertStatus(422);

        expect(TournamentPlayer::where('tournament_id', $fog->id)->count())->toBe(0)->and(TournamentPlayerMember::count())->toBe(0);
    });

    it('links a public garrison per member for allegiance rulesets', function () {
        $fog = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id, 'ruleset' => 'tos_fields_of_glory', 'team_size' => 2]);
        $allegiance = Allegiance::factory()->create();
        $garrison = Garrison::factory()->forAllegiance($allegiance)->create(['is_public' => true]);

        ($this->addTeam)(['display_name' => 'Garrison Team', 'members' => [
            ['display_name' => 'A', 'allegiance_id' => $allegiance->id, 'garrison_share_code' => $garrison->share_code],
            ['display_name' => 'B', 'allegiance_id' => $allegiance->id],
        ]], $fog)->assertOk();

        $members = TournamentPlayer::where('display_name', 'Garrison Team')->first()->members;
        expect($members[0]->garrison_id)->toBe($garrison->id)->and($members[1]->garrison_id)->toBeNull();
    });
});

describe('starting and running a team event', function () {
    it('only starts once every team is full and every member has declared', function () {
        ($this->team)('Half', [null]);
        ($this->team)('Full A', [null, null]);
        $sm = app(TournamentStateMachine::class);

        expect($sm->canTransitionTournamentTo($this->t, \App\Enums\TournamentStatusEnum::Active))
            ->toBe('1 team(s) incomplete — every member needs a name and a faction');

        TournamentPlayer::where('display_name', 'Half')->first()->members()->create(['display_name' => 'Late', 'faction' => 'guild', 'position' => 1]);
        TournamentPlayerMember::query()->update(['faction' => 'guild']);

        expect($sm->canTransitionTournamentTo($this->t->fresh(), \App\Enums\TournamentStatusEnum::Active))->toBeNull();
    });

    it('pairs, scores and ranks teams like any other competitor, listing the members in standings', function () {
        $t = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'team_size' => 2]);
        $a = ($this->team)('Alpha', [null, null], $t);
        $b = ($this->team)('Bravo', [null, null], $t);
        $round = TournamentRound::factory()->for($t)->create();
        TournamentGame::factory()->for($round, 'round')->withScore(9, 4)->create(['player_one_id' => $a->id, 'player_two_id' => $b->id]);

        $standings = collect(app(TournamentStandingsService::class)->compute($t))->keyBy('player_id');

        expect($standings[$a->id]['total_tp'])->toBe(3)->and($standings[$a->id]['members'])->toBe(['Alpha 0', 'Alpha 1'])
            ->and($standings[$b->id]['total_tp'])->toBe(0);
    });

    it('notifies every linked member when a round is paired', function () {
        Notification::fake();
        $t = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'team_size' => 2]);
        [$u1, $u2, $u3, $u4] = User::factory()->count(4)->create()->all();
        ($this->team)('Alpha', [$u1, $u2], $t);
        ($this->team)('Bravo', [$u3, $u4], $t);
        $round = TournamentRound::factory()->for($t)->create(['strategy_id' => \App\Models\Strategy::factory()->create()->id, 'deployment' => \App\Enums\DeploymentEnum::cases()[0], 'scheme_pool' => [1, 2, 3]]);
        \App\Models\Scheme::factory()->count(3)->create();

        $this->actingAs($this->owner)->postJson(route('tournaments.rounds.pair', [$t, $round]))->assertOk();

        foreach ([$u1, $u2, $u3, $u4] as $user) {
            Notification::assertSentTo($user, TournamentRoundPaired::class);
        }
    });

    it('never spawns a tracker game for a team event', function () {
        $t = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'team_size' => 2]);
        $round = TournamentRound::factory()->for($t)->create();
        $a = ($this->team)('Alpha', [User::factory()->create(), User::factory()->create()], $t);
        $b = ($this->team)('Bravo', [User::factory()->create(), User::factory()->create()], $t);
        TournamentGame::factory()->for($round, 'round')->create(['player_one_id' => $a->id, 'player_two_id' => $b->id]);

        app(\App\Services\TournamentTrackerGameFactory::class)->createForRound($t, $round);

        expect(Game::count())->toBe(0);
    });
});

describe('members acting for their team', function () {
    beforeEach(function () {
        $this->u1 = User::factory()->create();
        $this->u2 = User::factory()->create();
        $this->u3 = User::factory()->create();
        $this->live = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'team_size' => 2, 'allow_self_report' => true]);
        $this->alpha = ($this->team)('Alpha', [$this->u1, null], $this->live);
        $this->bravo = ($this->team)('Bravo', [$this->u2, $this->u3], $this->live);
        $this->round = TournamentRound::factory()->for($this->live)->inProgress()->create();
        $this->game = TournamentGame::factory()->for($this->round, 'round')->create(['player_one_id' => $this->alpha->id, 'player_two_id' => $this->bravo->id]);
        $this->score = ['player_one_score' => ['strategy' => 4, 'scheme' => 3], 'player_two_score' => ['strategy' => 1, 'scheme' => 2]];
    });

    it('lets a member report for the team and the other team’s member confirm', function () {
        $url = fn ($n) => route("tournaments.games.{$n}", [$this->live, $this->game]);

        $this->actingAs($this->u1)->post($url('report'), $this->score)->assertSessionHasNoErrors();
        expect($this->game->fresh()->reported_by_player_id)->toBe($this->alpha->id);

        $this->actingAs($this->u1)->post($url('report.confirm'))->assertSessionHasErrors('report');
        $this->actingAs($this->u3)->post($url('report.confirm'))->assertSessionHasNoErrors();
        expect($this->game->fresh()->result)->toBe(TournamentGameResultEnum::Completed);
    });

    it('lets a member check their team in', function () {
        $this->actingAs($this->u2)->post(route('tournaments.check_in', $this->live))->assertSessionHasNoErrors();

        expect($this->bravo->fresh()->checked_in_at)->not->toBeNull()->and($this->alpha->fresh()->checked_in_at)->toBeNull();
    });
});

describe('payloads', function () {
    it('shows members on the public page, hiding private garrison codes, and stops tracking leaders', function () {
        $fog = Tournament::factory()->active()->create(['ruleset' => 'tos_fields_of_glory', 'team_size' => 2]);
        $allegiance = Allegiance::factory()->create();
        $private = Garrison::factory()->forAllegiance($allegiance)->create(['is_public' => false]);
        $team = ($this->team)('Duo', [], $fog);
        $team->members()->create(['display_name' => 'Ann', 'allegiance_id' => $allegiance->id, 'garrison_id' => $private->id, 'position' => 0]);

        $this->get(route('tournaments.view', $fog))->assertInertia(fn (Assert $page) => $page
            ->where('tournament.team_size', 2)
            ->where('tournament.players.0.members.0.display_name', 'Ann')
            ->where('tournament.players.0.members.0.allegiance.name', $allegiance->name)
            ->where('tournament.players.0.members.0.garrison.name', $private->name)
            ->missing('tournament.players.0.members.0.garrison.share_code')
            ->where('ruleset.tracks_leaders', false));
    });

    it('still tracks leaders for individual Gaining Grounds events', function () {
        $solo = Tournament::factory()->create(['creator_id' => $this->owner->id]);

        $this->actingAs($this->owner)->get(route('tournaments.manage', $solo))
            ->assertInertia(fn (Assert $page) => $page->where('ruleset.tracks_leaders', true));
    });
});

describe('ringer teams', function () {
    it('lets a member-less ringer team be renamed without sending members, but a normal team still needs a full roster', function () {
        $t = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id, 'team_size' => 2]);
        $ringer = TournamentPlayer::factory()->for($t)->ringer()->create(['display_name' => 'Ringer Squad', 'faction' => null]);
        $normal = TournamentPlayer::factory()->for($t)->create(['display_name' => 'Regulars', 'faction' => null]);

        // empty roster is fine for the ringer (the flag isn't re-sent on update — the stored one counts)…
        $this->actingAs($this->owner)->putJson(route('tournaments.players.update', [$t, $ringer]), ['display_name' => 'Renamed Ringers', 'members' => []])->assertOk();
        expect($ringer->fresh()->display_name)->toBe('Renamed Ringers');

        // …but not for a team that isn't a ringer.
        $this->actingAs($this->owner)->putJson(route('tournaments.players.update', [$t, $normal]), ['members' => []])
            ->assertStatus(422)->assertJsonValidationErrors('members');
    });

    it('can have an optional roster: a ringer team with members is still valid', function () {
        $t = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id, 'team_size' => 2]);

        $this->actingAs($this->owner)->postJson(route('tournaments.players.add', $t), [
            'display_name' => 'Full Ringers', 'is_ringer' => true,
            'members' => [['display_name' => 'A', 'faction' => 'guild'], ['display_name' => 'B', 'faction' => 'guild']],
        ])->assertOk();

        expect(TournamentPlayer::where('display_name', 'Full Ringers')->first()->members)->toHaveCount(2);
    });

    it('does not make a ringer team block the start of a team event', function () {
        $t = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id, 'team_size' => 2]);
        TournamentPlayer::factory()->for($t)->ringer()->create(['faction' => null]);
        foreach (['A', 'B'] as $name) {
            $team = TournamentPlayer::factory()->for($t)->create(['display_name' => $name, 'faction' => null]);
            $team->members()->createMany([['display_name' => "{$name}1", 'faction' => 'guild', 'position' => 0], ['display_name' => "{$name}2", 'faction' => 'guild', 'position' => 1]]);
        }

        expect(app(TournamentStateMachine::class)->canTransitionTournamentTo($t, \App\Enums\TournamentStatusEnum::Active))->toBeNull();
    });
});
