<?php

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Models\TOS\Asset;
use App\Models\TOS\Envoy;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Stratagem;
use App\Models\TOS\Trigger;
use App\Models\TOS\Unit;

it('lists TOS allegiances', function () {
    Allegiance::factory()->count(2)->create();

    $this->getJson('/api/v1/tos/allegiances')
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta'])
        ->assertJsonCount(2, 'data');
});

it('filters TOS allegiances by type', function () {
    Allegiance::factory()->create(['type' => AllegianceTypeEnum::Earth]);
    Allegiance::factory()->create(['type' => AllegianceTypeEnum::Malifaux]);

    $this->getJson('/api/v1/tos/allegiances?type=earth')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'earth');
});

it('shows a single TOS allegiance by slug', function () {
    $allegiance = Allegiance::factory()->create();

    $this->getJson("/api/v1/tos/allegiances/{$allegiance->slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $allegiance->id);
});

it('lists TOS allegiance cards', function () {
    AllegianceCard::factory()->count(2)->create();

    $this->getJson('/api/v1/tos/allegiance-cards')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lists TOS units excluding combined arms children by default', function () {
    $parent = Unit::factory()->create();
    $child = Unit::factory()->create();
    $parent->update(['combined_arms_child_id' => $child->id]);

    $this->getJson('/api/v1/tos/units')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $parent->id);
});

it('lists TOS units including children when flagged', function () {
    $parent = Unit::factory()->create();
    $child = Unit::factory()->create();
    $parent->update(['combined_arms_child_id' => $child->id]);

    $this->getJson('/api/v1/tos/units?include_combined_arms_children=1')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('shows a single TOS unit by slug', function () {
    $unit = Unit::factory()->create();

    $this->getJson("/api/v1/tos/units/{$unit->slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $unit->id);
});

it('lists TOS assets', function () {
    Asset::factory()->count(2)->create();

    $this->getJson('/api/v1/tos/assets')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lists TOS envoys', function () {
    Envoy::factory()->count(2)->create();

    $this->getJson('/api/v1/tos/envoys')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lists TOS stratagems and filters by allegiance type', function () {
    Stratagem::factory()->create(['allegiance_id' => null, 'allegiance_type' => AllegianceTypeEnum::Earth->value]);
    Stratagem::factory()->create(['allegiance_id' => null, 'allegiance_type' => AllegianceTypeEnum::Malifaux->value]);

    $this->getJson('/api/v1/tos/stratagems?allegiance_type=earth')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.allegiance_type', 'earth');
});

it('lists TOS special unit rules', function () {
    SpecialUnitRule::factory()->count(3)->create();

    $this->getJson('/api/v1/tos/special-unit-rules')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('lists TOS abilities filtered to general only', function () {
    Ability::factory()->create(['is_general' => true]);
    Ability::factory()->create(['is_general' => false, 'allegiance_id' => Allegiance::factory()->create()->id]);

    $this->getJson('/api/v1/tos/abilities?is_general=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_general', true);
});

it('lists TOS actions', function () {
    Action::factory()->count(2)->create();

    $this->getJson('/api/v1/tos/actions')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lists TOS triggers', function () {
    Trigger::factory()->count(2)->create();

    $this->getJson('/api/v1/tos/triggers')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns 404 for missing TOS allegiance', function () {
    $this->getJson('/api/v1/tos/allegiances/nonexistent-slug')->assertNotFound();
});
