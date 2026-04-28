<?php

use App\Enums\CrewUpgradeModeEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Upgrade;
use App\Models\User;

beforeEach(function () {
    $strategy = Strategy::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    $schemes = Scheme::factory()->count(3)->create(['season' => PoolSeasonEnum::GainingGrounds0]);

    $this->master = Character::factory()->create();
    $this->upgrade = Upgrade::factory()->create([
        'domain' => UpgradeDomainTypeEnum::Crew->value,
        'power_bar_count' => 4,
    ]);
    $this->upgradeNoBar = Upgrade::factory()->create([
        'domain' => UpgradeDomainTypeEnum::Crew->value,
        'power_bar_count' => null,
    ]);
    $this->master->update(['crew_upgrade_mode' => CrewUpgradeModeEnum::SelectOne]);
    $this->master->upgrades()->attach([$this->upgrade->id, $this->upgradeNoBar->id]);

    $this->user = User::factory()->create();
    $this->game = Game::factory()->inProgress()->create([
        'creator_id' => $this->user->id,
        'strategy_id' => $strategy->id,
        'scheme_pool' => $schemes->pluck('id')->toArray(),
    ]);
    $this->player = GamePlayer::factory()->create([
        'game_id' => $this->game->id,
        'user_id' => $this->user->id,
        'slot' => 1,
        'master_id' => $this->master->id,
        'scheme_pool' => $schemes->pluck('id')->toArray(),
    ]);
});

it('persists crew upgrade power bar via the new endpoint', function () {
    $resp = $this->actingAs($this->user)->patchJson(
        route('games.play.crew-upgrade-power-bar', $this->game->uuid),
        ['upgrade_id' => $this->upgrade->id, 'current_power_bar' => 3],
    );

    $resp->assertOk()->assertJsonPath('success', true)->assertJsonPath('current_power_bar', 3);

    $this->player->refresh();
    expect($this->player->crew_upgrade_power_bars)->toBe([(string) $this->upgrade->id => 3]);
});

it('clamps current_power_bar to the upgrade max', function () {
    $resp = $this->actingAs($this->user)->patchJson(
        route('games.play.crew-upgrade-power-bar', $this->game->uuid),
        ['upgrade_id' => $this->upgrade->id, 'current_power_bar' => 99],
    );

    $resp->assertOk()->assertJsonPath('current_power_bar', 4);

    $this->player->refresh();
    expect($this->player->crew_upgrade_power_bars[(string) $this->upgrade->id])->toBe(4);
});

it('rejects upgrades with no power_bar_count', function () {
    $this->actingAs($this->user)->patchJson(
        route('games.play.crew-upgrade-power-bar', $this->game->uuid),
        ['upgrade_id' => $this->upgradeNoBar->id, 'current_power_bar' => 1],
    )->assertStatus(422);
});

it('rejects upgrades not on the player master', function () {
    $other = Upgrade::factory()->create([
        'domain' => UpgradeDomainTypeEnum::Crew->value,
        'power_bar_count' => 2,
    ]);
    // Attach to a different master so it's not on $this->master.
    Character::factory()->create()->upgrades()->attach($other->id);

    $this->actingAs($this->user)->patchJson(
        route('games.play.crew-upgrade-power-bar', $this->game->uuid),
        ['upgrade_id' => $other->id, 'current_power_bar' => 1],
    )->assertStatus(422);
});

it('preserves existing power-bar map when updating one entry', function () {
    $this->player->update(['crew_upgrade_power_bars' => ['999' => 1]]);

    $this->actingAs($this->user)->patchJson(
        route('games.play.crew-upgrade-power-bar', $this->game->uuid),
        ['upgrade_id' => $this->upgrade->id, 'current_power_bar' => 2],
    )->assertOk();

    $this->player->refresh();
    expect($this->player->crew_upgrade_power_bars)->toBe([
        '999' => 1,
        (string) $this->upgrade->id => 2,
    ]);
});

it('persists current_power_bar inside attached_upgrades JSON for a member', function () {
    $member = GameCrewMember::factory()->create([
        'game_id' => $this->game->id,
        'game_player_id' => $this->player->id,
        'attached_upgrades' => [],
    ]);

    $payload = [
        'attached_upgrades' => [
            ['id' => $this->upgrade->id, 'name' => 'Power Up', 'current_power_bar' => 2],
        ],
    ];

    $this->actingAs($this->user)
        ->patchJson(route('games.play.crew.update', ['game' => $this->game->uuid, 'gameCrewMember' => $member->id]), $payload)
        ->assertOk();

    $member->refresh();
    expect($member->attached_upgrades[0]['current_power_bar'])->toBe(2);
});
