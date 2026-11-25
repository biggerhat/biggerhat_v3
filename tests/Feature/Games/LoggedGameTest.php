<?php

use App\Enums\GameOriginEnum;
use App\Enums\GameStatusEnum;
use App\Models\Character;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\User;
use App\Notifications\Game\LoggedGameLinkRequested;
use Illuminate\Support\Facades\Notification;

/** The smallest game that can be logged: a result, the day, and each side's faction, master and points. */
function quickLog(array $over = []): array
{
    return array_replace_recursive([
        'format' => 'standard',
        'played_on' => now()->subDay()->toDateString(),
        'encounter_size' => 50,
        'result' => 'win',
        'me' => ['faction' => 'guild', 'master_name' => 'Lady Justice', 'total_points' => 7],
        'opponent' => ['faction' => 'arcanists', 'master_name' => 'Rasputina', 'total_points' => 4, 'opponent_name' => 'Sam'],
    ], $over);
}

it('logs a game from the quick fields and makes it a completed game of its own origin', function () {
    $me = User::factory()->create();

    $this->actingAs($me)->post(route('games.logged.store'), quickLog())->assertRedirect();

    $game = Game::query()->with('players')->firstOrFail();
    expect($game->origin)->toBe(GameOriginEnum::Logged)
        ->and($game->status)->toBe(GameStatusEnum::Completed)
        ->and($game->creator_id)->toBe($me->id)
        ->and($game->winner_id)->toBe($me->id)->and($game->winner_slot)->toBe(1)->and($game->is_tie)->toBeFalse()
        ->and($game->completed_at->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($game->players)->toHaveCount(2);
    expect($game->players[0]->user_id)->toBe($me->id)->and($game->players[0]->total_points)->toBe(7)
        ->and($game->players[1]->user_id)->toBeNull()->and($game->players[1]->opponent_name)->toBe('Sam')->and($game->players[1]->total_points)->toBe(4);
});

it('records a loss as the opponent winning and a tie as nobody', function () {
    $me = User::factory()->create();
    $this->actingAs($me)->post(route('games.logged.store'), quickLog(['result' => 'loss']));
    $this->actingAs($me)->post(route('games.logged.store'), quickLog(['result' => 'tie']));

    [$loss, $tie] = Game::query()->orderBy('id')->get()->all();
    expect($loss->winner_id)->toBeNull()->and($loss->winner_slot)->toBe(2)->and($loss->is_tie)->toBeFalse()
        ->and($tie->winner_slot)->toBeNull()->and($tie->is_tie)->toBeTrue();
});

it('records the turns, schemes and crews the tracker would have', function () {
    $me = User::factory()->create();
    $strategy = Strategy::factory()->create();
    $scheme = Scheme::factory()->create();
    $hired = Character::factory()->create();

    $this->actingAs($me)->post(route('games.logged.store'), quickLog([
        'strategy_id' => $strategy->id,
        'deployment' => 'corner',
        'scheme_pool' => [$scheme->id],
        'venue' => 'Friday league',
        'notes' => 'Close one',
        'turns' => [
            ['turn_number' => 1, 'me' => ['strategy_points' => 1, 'scheme_points' => 0], 'opponent' => ['strategy_points' => 0, 'scheme_points' => 1, 'scheme_id' => $scheme->id, 'scheme_action' => 'scored']],
            ['turn_number' => 2, 'me' => ['strategy_points' => 2, 'scheme_points' => 4, 'scheme_id' => $scheme->id, 'scheme_action' => 'scored'], 'opponent' => ['strategy_points' => 3, 'scheme_points' => 0]],
        ],
        'me' => ['models' => [['character_id' => $hired->id, 'killed' => true]]],
    ]))->assertSessionHasNoErrors();

    $game = Game::query()->with(['turns', 'crewMembers'])->firstOrFail();
    expect($game->strategy_id)->toBe($strategy->id)->and($game->venue)->toBe('Friday league')->and($game->notes)->toBe('Close one')
        ->and($game->turns)->toHaveCount(4)
        ->and($game->turns->sum('points_scored'))->toBe(11)
        ->and($game->crewMembers)->toHaveCount(1)
        ->and($game->crewMembers[0]->is_killed)->toBeTrue()->and($game->crewMembers[0]->current_health)->toBe(0);
});

it('keeps a Bonanza Brawl game free of strategy, deployment and schemes', function () {
    $me = User::factory()->create();
    $strategy = Strategy::factory()->create();

    $this->actingAs($me)->post(route('games.logged.store'), quickLog(['format' => 'bonanza_brawl', 'encounter_size' => 11, 'strategy_id' => $strategy->id, 'deployment' => 'corner']))->assertSessionHasNoErrors();

    $game = Game::query()->firstOrFail();
    expect($game->strategy_id)->toBeNull()->and($game->deployment)->toBeNull()->and($game->scheme_pool)->toBe([]);
});

it('rejects points that do not add up, a future day, both sides attacking and a missing opponent', function () {
    $me = User::factory()->create();
    $post = fn (array $over) => $this->actingAs($me)->post(route('games.logged.store'), quickLog($over));

    $post(['turns' => [['turn_number' => 1, 'me' => ['strategy_points' => 1, 'scheme_points' => 1], 'opponent' => []]]])->assertSessionHasErrors('me.total_points');
    $post(['played_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('played_on');
    $post(['me' => ['role' => 'attacker'], 'opponent' => ['role' => 'attacker']])->assertSessionHasErrors('opponent.role');
    $post(['opponent' => ['opponent_name' => '']])->assertSessionHasErrors('opponent.opponent_name');
    $post(['result' => 'draw'])->assertSessionHasErrors('result');
    $post(['me' => ['master_name' => null]])->assertSessionHasErrors('me.master_name');
    expect(Game::count())->toBe(0);
});

it('lets only the owner edit and delete, and edits replace the turns and crews', function () {
    $me = User::factory()->create();
    $this->actingAs($me)->post(route('games.logged.store'), quickLog(['turns' => [['turn_number' => 1, 'me' => ['strategy_points' => 7], 'opponent' => ['strategy_points' => 4]]]]));
    $game = Game::firstOrFail();

    $this->actingAs(User::factory()->create())->get(route('games.logged.edit', $game))->assertNotFound();
    $this->actingAs(User::factory()->create())->put(route('games.logged.update', $game), quickLog())->assertNotFound();
    $this->actingAs(User::factory()->create())->delete(route('games.logged.destroy', $game))->assertNotFound();

    $this->actingAs($me)->get(route('games.logged.edit', $game))->assertOk();
    $this->actingAs($me)->put(route('games.logged.update', $game), quickLog(['result' => 'loss', 'me' => ['total_points' => 2]]))->assertRedirect(route('games.logged.show', $game));

    $game->refresh()->load(['turns', 'players']);
    expect($game->winner_slot)->toBe(2)->and($game->turns)->toHaveCount(0)->and($game->players[0]->total_points)->toBe(2);

    $this->actingAs($me)->delete(route('games.logged.destroy', $game))->assertRedirect(route('games.index'));
    expect(Game::count())->toBe(0)->and(Game::withTrashed()->count())->toBe(1);
});

it('keeps a logged game out of everything that belongs to a game being played', function () {
    $me = User::factory()->create();
    $this->actingAs($me)->post(route('games.logged.store'), quickLog());
    $game = Game::firstOrFail();

    $this->actingAs($me)->get(route('games.show', $game))->assertRedirect(route('games.logged.show', $game));
    $this->get(route('games.summary', $game))->assertRedirect(route('games.logged.show', $game));
    $this->actingAs($me)->get(route('games.board', $game))->assertNotFound();
    $this->actingAs($me)->post(route('games.abandon', $game))->assertNotFound();
    $this->actingAs($me)->post(route('games.toggle_observable', $game))->assertNotFound();
    $this->get(route('games.observe', $game))->assertNotFound();
});

it('shows a logged game to its owner, hides it from others until it is public, and lets anyone see a public one', function () {
    $me = User::factory()->create();
    $this->actingAs($me)->post(route('games.logged.store'), quickLog());
    $game = Game::firstOrFail();

    $this->actingAs($me)->get(route('games.logged.show', $game))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('games.logged.show', $game))->assertNotFound();
    auth()->logout();
    $this->get(route('games.logged.show', $game))->assertNotFound();

    $game->update(['is_public' => true]);
    $this->get(route('games.logged.show', $game))->assertOk();
});

it('asks a linked opponent to confirm, and only a confirmed link puts the game in their record', function () {
    Notification::fake();
    $me = User::factory()->create();
    $sam = User::factory()->create();

    $this->actingAs($me)->post(route('games.logged.store'), quickLog(['result' => 'loss', 'opponent' => ['opponent_name' => null, 'linked_user_id' => $sam->id]]))->assertSessionHasNoErrors();
    $game = Game::firstOrFail();
    $theirs = $game->players()->where('slot', 2)->first();

    Notification::assertSentTo($sam, LoggedGameLinkRequested::class);
    expect($theirs->link_status)->toBe('pending')->and($theirs->user_id)->toBeNull()->and($theirs->opponent_name)->toBe($sam->name);
    // the invited player can see it, a stranger cannot
    $this->actingAs($sam)->get(route('games.logged.show', $game))->assertOk();
    $this->actingAs(User::factory()->create())->post(route('games.logged.confirm', $game))->assertNotFound();

    $this->actingAs($sam)->post(route('games.logged.confirm', $game))->assertRedirect();
    $theirs->refresh();
    expect($theirs->link_status)->toBe('confirmed')->and($theirs->user_id)->toBe($sam->id)->and($game->refresh()->winner_id)->toBe($sam->id);
});

it('lets an invited opponent decline, and nothing is added to them', function () {
    Notification::fake();
    $me = User::factory()->create();
    $sam = User::factory()->create();
    $this->actingAs($me)->post(route('games.logged.store'), quickLog(['opponent' => ['opponent_name' => 'Sam', 'linked_user_id' => $sam->id]]));
    $game = Game::firstOrFail();

    $this->actingAs($sam)->post(route('games.logged.decline', $game))->assertRedirect();
    $theirs = GamePlayer::where('game_id', $game->id)->where('slot', 2)->first();
    expect($theirs->link_status)->toBe('declined')->and($theirs->user_id)->toBeNull();
    $this->actingAs($sam)->get(route('games.logged.show', $game))->assertNotFound();
});

it('does not let you link yourself', function () {
    $me = User::factory()->create();
    $this->actingAs($me)->post(route('games.logged.store'), quickLog(['opponent' => ['linked_user_id' => $me->id]]))->assertSessionHasErrors('opponent.linked_user_id');
});

it('needs an account to log and searches models for the form', function () {
    $this->post(route('games.logged.store'), quickLog())->assertRedirect(route('login'));
    $this->get(route('games.logged.create'))->assertRedirect(route('login'));
    $this->getJson(route('games.logged.characters', ['q' => 'nec']))->assertUnauthorized();

    Character::factory()->create(['name' => 'Rogue Necromancy', 'display_name' => 'Rogue Necromancy', 'title' => null]);
    $this->actingAs(User::factory()->create())->getJson(route('games.logged.characters', ['q' => 'necro']))->assertOk()->assertJsonPath('characters.0.name', 'Rogue Necromancy');
    $this->getJson(route('games.logged.characters', ['q' => 'n']))->assertOk()->assertJsonPath('characters', []);
});

it('shows up in the owner list of finished games', function () {
    $me = User::factory()->create();
    $this->actingAs($me)->post(route('games.logged.store'), quickLog());

    $this->actingAs($me)->get(route('games.index'))->assertInertia(fn ($page) => $page->has('recent_games', 1));
});
