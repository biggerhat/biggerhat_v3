<?php

use App\Enums\GameModeTypeEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Upgrade;

// ── Enum Tests ──────────────────────────────────────────────────────────────

it('has the correct game mode type cases', function () {
    $cases = GameModeTypeEnum::cases();

    expect($cases)->toHaveCount(4);
    expect(array_map(fn ($c) => $c->value, $cases))->toBe(['standard', 'campaign', 'cooperative', 'custom']);
});

it('generates select options', function () {
    $options = GameModeTypeEnum::toSelectOptions();

    expect($options)->toHaveCount(4);
    expect($options[0])->toBe(['name' => 'Standard', 'value' => 'standard']);
    expect($options[1])->toBe(['name' => 'Campaign', 'value' => 'campaign']);
});

it('generates labels from case names', function () {
    expect(GameModeTypeEnum::Standard->label())->toBe('Standard');
    expect(GameModeTypeEnum::Campaign->label())->toBe('Campaign');
    expect(GameModeTypeEnum::Cooperative->label())->toBe('Cooperative');
    expect(GameModeTypeEnum::Custom->label())->toBe('Custom');
});

// ── Scope Tests ─────────────────────────────────────────────────────────────

it('scopes characters to standard mode by default', function () {
    Character::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Character::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);

    expect(Character::standard()->count())->toBe(1);
    expect(Character::standard()->first()->game_mode_type)->toBe(GameModeTypeEnum::Standard);
});

it('scopes characters to a specific game mode', function () {
    Character::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Character::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);
    Character::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);

    expect(Character::forGameMode(GameModeTypeEnum::Campaign)->count())->toBe(2);
});

it('scopes upgrades to standard mode', function () {
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Cooperative]);

    expect(Upgrade::standard()->count())->toBe(1);
});

it('scopes actions to standard mode', function () {
    Action::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Action::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);

    expect(Action::standard()->count())->toBe(1);
});

it('scopes abilities to standard mode', function () {
    Ability::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Ability::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);

    expect(Ability::standard()->count())->toBe(1);
});

it('scopes keywords to standard mode', function () {
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Custom]);

    expect(Keyword::standard()->count())->toBe(1);
});

// ── API V1: game_mode_type field in responses ───────────────────────────────

it('includes game_mode_type in character API response', function () {
    Character::factory()->create(['is_hidden' => false]);

    $response = $this->getJson('/api/v1/characters');

    $response->assertOk();
    expect($response->json('data.0.game_mode_type'))->toBe('standard');
    expect($response->json('data.0.game_mode_type_label'))->toBe('Standard');
});

it('includes game_mode_type in upgrade API response', function () {
    Upgrade::factory()->create();

    $response = $this->getJson('/api/v1/upgrades');

    $response->assertOk();
    expect($response->json('data.0.game_mode_type'))->toBe('standard');
});

it('includes game_mode_type in keyword API response', function () {
    Keyword::factory()->create();

    $response = $this->getJson('/api/v1/keywords');

    $response->assertOk();
    expect($response->json('data.0.game_mode_type'))->toBe('standard');
});

// ── API V1: game_mode_type query param filtering ────────────────────────────

it('filters characters by game_mode_type query param', function () {
    Character::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'is_hidden' => false]);
    Character::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'is_hidden' => false]);

    $standard = $this->getJson('/api/v1/characters?game_mode_type=standard');
    $campaign = $this->getJson('/api/v1/characters?game_mode_type=campaign');

    $standard->assertOk();
    $campaign->assertOk();
    expect($standard->json('data'))->toHaveCount(1);
    expect($campaign->json('data'))->toHaveCount(1);
    expect($campaign->json('data.0.game_mode_type'))->toBe('campaign');
});

it('filters upgrades by game_mode_type query param', function () {
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Upgrade::factory()->create(['game_mode_type' => GameModeTypeEnum::Cooperative]);

    $standard = $this->getJson('/api/v1/upgrades?game_mode_type=standard');
    $cooperative = $this->getJson('/api/v1/upgrades?game_mode_type=cooperative');

    expect($standard->json('data'))->toHaveCount(1);
    expect($cooperative->json('data'))->toHaveCount(1);
    expect($cooperative->json('data.0.game_mode_type'))->toBe('cooperative');
});

it('filters actions by game_mode_type query param', function () {
    Action::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Action::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);

    $standard = $this->getJson('/api/v1/actions?game_mode_type=standard');
    $campaign = $this->getJson('/api/v1/actions?game_mode_type=campaign');

    expect($standard->json('data'))->toHaveCount(1);
    expect($campaign->json('data'))->toHaveCount(1);
});

it('filters abilities by game_mode_type query param', function () {
    Ability::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Ability::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);

    $standard = $this->getJson('/api/v1/abilities?game_mode_type=standard');
    $campaign = $this->getJson('/api/v1/abilities?game_mode_type=campaign');

    expect($standard->json('data'))->toHaveCount(1);
    expect($campaign->json('data'))->toHaveCount(1);
});

it('filters keywords by game_mode_type query param', function () {
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard]);
    Keyword::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign]);

    $standard = $this->getJson('/api/v1/keywords?game_mode_type=standard');
    $campaign = $this->getJson('/api/v1/keywords?game_mode_type=campaign');

    expect($standard->json('data'))->toHaveCount(1);
    expect($campaign->json('data'))->toHaveCount(1);
});

it('defaults to standard when game_mode_type param is omitted', function () {
    Character::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'is_hidden' => false]);
    Character::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'is_hidden' => false]);

    $response = $this->getJson('/api/v1/characters');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.game_mode_type'))->toBe('standard');
});

it('defaults to standard when game_mode_type param is invalid', function () {
    Character::factory()->create(['game_mode_type' => GameModeTypeEnum::Standard, 'is_hidden' => false]);
    Character::factory()->create(['game_mode_type' => GameModeTypeEnum::Campaign, 'is_hidden' => false]);

    $response = $this->getJson('/api/v1/characters?game_mode_type=nonexistent');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.game_mode_type'))->toBe('standard');
});

// ── Negative test: campaign content excluded from public pages ───────────────

it('excludes campaign characters from public API by default', function () {
    Character::factory()->create([
        'game_mode_type' => GameModeTypeEnum::Campaign,
        'name' => 'Campaign Only Hero',
        'is_hidden' => false,
    ]);
    Character::factory()->create([
        'game_mode_type' => GameModeTypeEnum::Standard,
        'name' => 'Standard Hero',
        'is_hidden' => false,
    ]);

    $response = $this->getJson('/api/v1/characters');

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('Standard Hero');
    expect($names)->not->toContain('Campaign Only Hero');
});

// ── Model cast test ─────────────────────────────────────────────────────────

it('casts game_mode_type to enum on all models', function () {
    $character = Character::factory()->create();
    $upgrade = Upgrade::factory()->create();
    $action = Action::factory()->create();
    $ability = Ability::factory()->create();
    $keyword = Keyword::factory()->create();

    expect($character->game_mode_type)->toBeInstanceOf(GameModeTypeEnum::class);
    expect($upgrade->game_mode_type)->toBeInstanceOf(GameModeTypeEnum::class);
    expect($action->game_mode_type)->toBeInstanceOf(GameModeTypeEnum::class);
    expect($ability->game_mode_type)->toBeInstanceOf(GameModeTypeEnum::class);
    expect($keyword->game_mode_type)->toBeInstanceOf(GameModeTypeEnum::class);
});
