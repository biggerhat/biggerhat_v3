<?php

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Models\TOS\Asset;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Stratagem;
use App\Models\TOS\Trigger;
use App\Models\TOS\Unit;

// ============================================================
// Pagination + meta envelope
// ============================================================

it('returns standard Laravel paginated envelope', function () {
    Allegiance::factory()->count(3)->create();

    $resp = $this->getJson('/api/v1/tos/allegiances');

    $resp->assertOk()
        ->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'last_page', 'per_page', 'total', 'links'],
        ]);
});

it('respects per_page parameter and caps at 100', function () {
    Allegiance::factory()->count(5)->create();

    $resp = $this->getJson('/api/v1/tos/allegiances?per_page=2');
    expect($resp->json('meta.per_page'))->toBe(2);
    expect($resp->json('data'))->toHaveCount(2);

    $respCap = $this->getJson('/api/v1/tos/allegiances?per_page=999');
    expect($respCap->json('meta.per_page'))->toBe(100);
});

// ============================================================
// Allegiances
// ============================================================

it('returns the documented allegiance fields', function () {
    $allegiance = Allegiance::factory()->create([
        'name' => 'Test Allegiance',
        'type' => AllegianceTypeEnum::Earth,
        'is_syndicate' => false,
    ]);

    $resp = $this->getJson("/api/v1/tos/allegiances/{$allegiance->slug}");

    $resp->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'short_name', 'slug', 'type', 'type_label', 'is_syndicate', 'description', 'logo_path', 'color_slug', 'sort_order'],
        ])
        ->assertJsonPath('data.name', 'Test Allegiance')
        ->assertJsonPath('data.type', 'earth')
        ->assertJsonPath('data.is_syndicate', false);
});

it('filters allegiances by syndicate flag', function () {
    Allegiance::factory()->create(['is_syndicate' => true]);
    Allegiance::factory()->create(['is_syndicate' => false]);

    $resp = $this->getJson('/api/v1/tos/allegiances?is_syndicate=1');

    expect($resp->json('data'))->toHaveCount(1);
    expect($resp->json('data.0.is_syndicate'))->toBeTrue();
});

it('searches allegiances by name', function () {
    Allegiance::factory()->create(['name' => "King's Empire"]);
    Allegiance::factory()->create(['name' => 'Cult of the Burning Man']);

    $resp = $this->getJson('/api/v1/tos/allegiances?search=King');

    expect($resp->json('data'))->toHaveCount(1);
    expect($resp->json('data.0.name'))->toBe("King's Empire");
});

// ============================================================
// Allegiance Cards
// ============================================================

it('returns allegiance card with parent allegiance and abilities', function () {
    $allegiance = Allegiance::factory()->create();
    $card = AllegianceCard::factory()->create(['allegiance_id' => $allegiance->id]);
    $abilityA = Ability::factory()->create();
    $abilityB = Ability::factory()->create();
    $card->abilities()->attach([$abilityA->id, $abilityB->id]);

    $resp = $this->getJson("/api/v1/tos/allegiance-cards/{$card->slug}");

    $resp->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'slug', 'type', 'body', 'image_path', 'allegiance_id', 'allegiance' => ['id', 'slug'], 'abilities'],
        ])
        ->assertJsonPath('data.allegiance.id', $allegiance->id);
    expect($resp->json('data.abilities'))->toHaveCount(2);
});

it('filters allegiance cards by allegiance slug', function () {
    $a1 = Allegiance::factory()->create();
    $a2 = Allegiance::factory()->create();
    AllegianceCard::factory()->create(['allegiance_id' => $a1->id]);
    AllegianceCard::factory()->create(['allegiance_id' => $a2->id]);

    $resp = $this->getJson("/api/v1/tos/allegiance-cards?allegiance={$a1->slug}");

    expect($resp->json('data'))->toHaveCount(1);
});

it('returns 404 for missing allegiance card', function () {
    $this->getJson('/api/v1/tos/allegiance-cards/nope')->assertNotFound();
});

// ============================================================
// Units
// ============================================================

it('returns unit with sides, sculpts, allegiances, and special rules', function () {
    $allegiance = Allegiance::factory()->create();
    $unit = Unit::factory()->withSides()->create();
    $unit->allegiances()->attach($allegiance->id);
    $rule = SpecialUnitRule::factory()->create(['name' => 'Commander', 'slug' => 'commander']);
    $unit->specialUnitRules()->attach($rule->id, ['parameters' => null]);

    $resp = $this->getJson("/api/v1/tos/units/{$unit->slug}");

    $resp->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'slug', 'scrip', 'tactics', 'restriction',
                'sides' => [['side', 'speed', 'defense', 'willpower', 'armor']],
                'sculpts',
                'allegiances',
                'special_unit_rules',
            ],
        ]);
    expect($resp->json('data.sides'))->toHaveCount(2);
    expect($resp->json('data.allegiances.0.id'))->toBe($allegiance->id);
    expect($resp->json('data.special_unit_rules.0.slug'))->toBe('commander');
});

it('filters units by allegiance slug', function () {
    $a1 = Allegiance::factory()->create();
    $a2 = Allegiance::factory()->create();
    $u1 = Unit::factory()->create();
    $u2 = Unit::factory()->create();
    $u1->allegiances()->attach($a1->id);
    $u2->allegiances()->attach($a2->id);

    $resp = $this->getJson("/api/v1/tos/units?allegiance={$a1->slug}");

    expect($resp->json('data'))->toHaveCount(1);
    expect($resp->json('data.0.id'))->toBe($u1->id);
});

it('filters units by special rule slug', function () {
    $commander = SpecialUnitRule::factory()->create(['slug' => 'commander', 'name' => 'Commander']);
    $titan = SpecialUnitRule::factory()->create(['slug' => 'titan', 'name' => 'Titan']);
    $u1 = Unit::factory()->create();
    $u2 = Unit::factory()->create();
    $u1->specialUnitRules()->attach($commander->id);
    $u2->specialUnitRules()->attach($titan->id);

    $resp = $this->getJson('/api/v1/tos/units?special_rule=commander');

    expect($resp->json('data'))->toHaveCount(1);
    expect($resp->json('data.0.id'))->toBe($u1->id);
});

it('filters units by neutral restriction', function () {
    Unit::factory()->create(['restriction' => AllegianceTypeEnum::Earth->value]);
    Unit::factory()->create(['restriction' => AllegianceTypeEnum::Malifaux->value]);
    Unit::factory()->create(['restriction' => null]);

    $resp = $this->getJson('/api/v1/tos/units?restriction=earth');

    expect($resp->json('data'))->toHaveCount(1);
    expect($resp->json('data.0.restriction'))->toBe('earth');
});

it('searches units by name OR title', function () {
    Unit::factory()->create(['name' => 'Earth Mover', 'title' => null]);
    Unit::factory()->create(['name' => 'Random', 'title' => 'Earth Bound']);
    Unit::factory()->create(['name' => 'Other', 'title' => null]);

    $resp = $this->getJson('/api/v1/tos/units?search=Earth');

    expect($resp->json('data'))->toHaveCount(2);
});

it('returns 404 for missing unit', function () {
    $this->getJson('/api/v1/tos/units/nope')->assertNotFound();
});

// ============================================================
// Assets
// ============================================================

it('returns asset with allegiances, abilities, actions, and limits', function () {
    $allegiance = Allegiance::factory()->create();
    $unit = Unit::factory()->create();
    $asset = Asset::factory()->restrictedByUnit($unit)->create(['name' => 'Earth Mover']);
    $asset->allegiances()->attach($allegiance->id);
    $ability = Ability::factory()->create();
    $asset->abilities()->attach($ability->id);
    $action = Action::factory()->create();
    $asset->actions()->attach($action->id);

    $resp = $this->getJson("/api/v1/tos/assets/{$asset->slug}");

    $resp->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'slug', 'scrip_cost', 'disable_count', 'scrap_count', 'body',
                'allegiances',
                'abilities',
                'actions',
                'limits' => [['limit_type', 'parameter_type']],
            ],
        ]);
    expect($resp->json('data.allegiances'))->toHaveCount(1);
    expect($resp->json('data.limits'))->toHaveCount(1);
});

it('filters assets by allegiance slug', function () {
    $a1 = Allegiance::factory()->create();
    $a2 = Allegiance::factory()->create();
    $asset1 = Asset::factory()->create();
    $asset2 = Asset::factory()->create();
    $asset1->allegiances()->attach($a1->id);
    $asset2->allegiances()->attach($a2->id);

    $resp = $this->getJson("/api/v1/tos/assets?allegiance={$a1->slug}");

    expect($resp->json('data'))->toHaveCount(1);
    expect($resp->json('data.0.id'))->toBe($asset1->id);
});

it('returns 404 for missing asset', function () {
    $this->getJson('/api/v1/tos/assets/nope')->assertNotFound();
});

// ============================================================
// Stratagems
// ============================================================

it('returns stratagem with parent allegiance', function () {
    $allegiance = Allegiance::factory()->create();
    $stratagem = Stratagem::factory()->create([
        'allegiance_id' => $allegiance->id,
        'tactical_cost' => 2,
        'name' => 'Forced March',
    ]);

    $resp = $this->getJson("/api/v1/tos/stratagems/{$stratagem->slug}");

    $resp->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'slug', 'tactical_cost', 'effect', 'allegiance_id', 'allegiance_type', 'allegiance' => ['id', 'slug']],
        ])
        ->assertJsonPath('data.tactical_cost', 2)
        ->assertJsonPath('data.allegiance.id', $allegiance->id);
});

it('filters stratagems by exact allegiance slug', function () {
    $a1 = Allegiance::factory()->create();
    $a2 = Allegiance::factory()->create();
    Stratagem::factory()->create(['allegiance_id' => $a1->id, 'allegiance_type' => null]);
    Stratagem::factory()->create(['allegiance_id' => $a2->id, 'allegiance_type' => null]);

    $resp = $this->getJson("/api/v1/tos/stratagems?allegiance={$a1->slug}");

    expect($resp->json('data'))->toHaveCount(1);
});

it('returns 404 for missing stratagem', function () {
    $this->getJson('/api/v1/tos/stratagems/nope')->assertNotFound();
});

// ============================================================
// Special Unit Rules
// ============================================================

it('returns a special unit rule', function () {
    $rule = SpecialUnitRule::factory()->create(['name' => 'Commander', 'slug' => 'commander']);

    $resp = $this->getJson("/api/v1/tos/special-unit-rules/{$rule->slug}");

    $resp->assertOk()
        ->assertJsonPath('data.id', $rule->id)
        ->assertJsonPath('data.name', 'Commander')
        ->assertJsonPath('data.slug', 'commander');
});

it('searches special unit rules by name', function () {
    SpecialUnitRule::factory()->create(['name' => 'Commander', 'slug' => 'commander']);
    SpecialUnitRule::factory()->create(['name' => 'Titan', 'slug' => 'titan']);

    $resp = $this->getJson('/api/v1/tos/special-unit-rules?search=Comm');

    expect($resp->json('data'))->toHaveCount(1);
    expect($resp->json('data.0.slug'))->toBe('commander');
});

// ============================================================
// Abilities
// ============================================================

it('filters abilities by allegiance slug', function () {
    $a1 = Allegiance::factory()->create();
    $a2 = Allegiance::factory()->create();
    Ability::factory()->create(['is_general' => false, 'allegiance_id' => $a1->id]);
    Ability::factory()->create(['is_general' => false, 'allegiance_id' => $a2->id]);
    Ability::factory()->create(['is_general' => true, 'allegiance_id' => null]);

    $resp = $this->getJson("/api/v1/tos/abilities?allegiance={$a1->slug}");

    expect($resp->json('data'))->toHaveCount(1);
});

it('returns ability show with allegiance', function () {
    $allegiance = Allegiance::factory()->create();
    $ability = Ability::factory()->create(['is_general' => false, 'allegiance_id' => $allegiance->id]);

    $resp = $this->getJson("/api/v1/tos/abilities/{$ability->slug}");

    $resp->assertOk()
        ->assertJsonPath('data.id', $ability->id)
        ->assertJsonPath('data.allegiance.id', $allegiance->id);
});

// ============================================================
// Actions
// ============================================================

it('returns action with triggers and types', function () {
    $action = Action::factory()->melee()->create();
    $trigger = Trigger::factory()->create();
    $action->triggers()->attach($trigger->id, ['sort_order' => 1]);

    $resp = $this->getJson("/api/v1/tos/actions/{$action->slug}");

    $resp->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'slug', 'av', 'tn', 'range', 'strength', 'is_piercing', 'is_accurate', 'is_area', 'types', 'triggers'],
        ]);
    expect($resp->json('data.triggers'))->toHaveCount(1);
});

it('searches actions by name', function () {
    Action::factory()->create(['name' => 'Combat Strike']);
    Action::factory()->create(['name' => 'Magical Blast']);

    $resp = $this->getJson('/api/v1/tos/actions?search=Combat');

    expect($resp->json('data'))->toHaveCount(1);
});

// ============================================================
// Triggers
// ============================================================

it('returns a trigger by slug', function () {
    $trigger = Trigger::factory()->create();

    $resp = $this->getJson("/api/v1/tos/triggers/{$trigger->slug}");

    $resp->assertOk()
        ->assertJsonPath('data.id', $trigger->id);
});

it('searches triggers by name', function () {
    Trigger::factory()->create(['name' => 'Critical Strike']);
    Trigger::factory()->create(['name' => 'Dodge']);

    $resp = $this->getJson('/api/v1/tos/triggers?search=Critical');

    expect($resp->json('data'))->toHaveCount(1);
});

// ============================================================
// Cross-cutting: every endpoint responds with 200 + paginated envelope on empty
// ============================================================

it('every list endpoint returns 200 with paginated envelope when empty', function (string $path) {
    $resp = $this->getJson($path);

    $resp->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta'])
        ->assertJsonPath('meta.total', 0);
})->with([
    '/api/v1/tos/allegiances',
    '/api/v1/tos/allegiance-cards',
    '/api/v1/tos/units',
    '/api/v1/tos/assets',
    '/api/v1/tos/stratagems',
    '/api/v1/tos/special-unit-rules',
    '/api/v1/tos/abilities',
    '/api/v1/tos/actions',
    '/api/v1/tos/triggers',
]);
