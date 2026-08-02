<?php

use App\Enums\GameStatusEnum;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create()->assignRole('super_admin');
});

it('blocks non-super_admin users from the games page', function () {
    $regular = User::factory()->create();
    $this->actingAs($regular);

    $this->get(route('admin.games.index'))->assertForbidden();
});

it('renders the admin games page for a super_admin', function () {
    $this->actingAs($this->admin);
    Game::factory()->create(['name' => 'Test Game']);

    $this->get(route('admin.games.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Games/Index')
            ->has('games.data', 1)
            ->where('games.data.0.name', 'Test Game')
            ->has('statuses')
            ->has('formats')
        );
});

it('falls back to the default sort when an arbitrary column is requested', function () {
    $this->actingAs($this->admin);
    Game::factory()->create();

    $this->get(route('admin.games.index', ['sort' => 'creator_id; DROP TABLE users']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.sort', 'updated_at'));
});

it('filters by status', function () {
    $this->actingAs($this->admin);
    Game::factory()->create(['status' => GameStatusEnum::Completed->value]);
    Game::factory()->create(['status' => GameStatusEnum::Setup->value]);

    $this->get(route('admin.games.index', ['status' => 'completed']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('games.data', 1)
            ->where('games.data.0.status', 'completed')
        );
});

it('filters by format', function () {
    $this->actingAs($this->admin);
    Game::factory()->bonanza()->create();
    Game::factory()->create();

    $this->get(route('admin.games.index', ['format' => 'bonanza_brawl']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('games.data', 1)
            ->where('games.data.0.format', 'bonanza_brawl')
        );
});

it('filters by is_solo', function () {
    $this->actingAs($this->admin);
    Game::factory()->solo()->create();
    Game::factory()->create(['is_solo' => false]);

    $this->get(route('admin.games.index', ['is_solo' => '1']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('games.data', 1)
            ->where('games.data.0.is_solo', true)
        );
});

it('search matches the creator\'s name', function () {
    $this->actingAs($this->admin);
    $creator = User::factory()->create(['name' => 'Findable Creator']);
    Game::factory()->create(['creator_id' => $creator->id]);
    Game::factory()->create();

    $this->get(route('admin.games.index', ['search' => 'Findable']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('games.data', 1));
});

it('search matches a non-creator player\'s name', function () {
    $this->actingAs($this->admin);
    $game = Game::factory()->create();
    $player = User::factory()->create(['name' => 'Findable Player']);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $player->id, 'slot' => 2]);
    Game::factory()->create();

    $this->get(route('admin.games.index', ['search' => 'Findable Player']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('games.data', 1)
            ->where('games.data.0.id', $game->id)
        );
});

it('sorts by current_turn ascending', function () {
    $this->actingAs($this->admin);
    Game::factory()->create(['current_turn' => 3]);
    Game::factory()->create(['current_turn' => 1]);

    $this->get(route('admin.games.index', ['sort' => 'current_turn', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('games.data.0.current_turn', 1)
            ->where('games.data.1.current_turn', 3)
        );
});
