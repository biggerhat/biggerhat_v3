<?php

use App\Enums\PermissionEnum;
use App\Models\Cooperative\CoopClass;
use App\Models\Cooperative\CoopEncounter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([PermissionEnum::ViewCampaignCatalog, PermissionEnum::EditCampaignCatalog, PermissionEnum::DeleteCampaignCatalog] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
});

function coopAdmin(array $perms = ['view_campaign_catalog', 'edit_campaign_catalog', 'delete_campaign_catalog']): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo($perms);

    return $user;
}

function classPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Gunslinger', 'slug' => 'gunslinger', 'role' => 'firepower', 'is_unique' => false,
        'resistance' => 12, 'speed' => 5, 'max_health' => 5, 'elite_max_health' => 10,
        'abilities' => [['name' => 'Dirty Tricks', 'text' => 'Once per activation.']],
        'actions' => [['kind' => 'attack', 'name' => 'Hail of Gunfire', 'rg' => '12"', 'skl' => '13', 'rst' => 'Df', 'tn' => '-', 'dmg' => '2', 'text' => '', 'triggers' => [['name' => 'Suppress', 'text' => 'Remove a marker.']]]],
        'behavior' => [['flip_min' => 10, 'flip_max' => null, 'ideal_range' => '10"', 'effects' => ['Hail of Gunfire', 'Hail of Gunfire']]],
        'target_preference' => ['Lowest Df', 'Most wounded', 'Closest'],
        'elite_modifications' => 'Maximum health +4.',
        'is_published' => true, 'sort_order' => 0,
    ], $overrides);
}

function encounterPayload(array $overrides = []): array
{
    return array_merge([
        'number' => 1, 'name' => 'Smash and Grab', 'slug' => 'smash-and-grab', 'narrative' => 'Text', 'encounter_size' => 36, 'max_hand_size' => 5,
        'board_size' => '36" × 36"', 'challenge_mode' => null,
        'phases' => [['title' => 'Opening', 'role_order' => [['role' => 'brawler', 'class_slug' => null]], 'setup' => ['Place terrain.'], 'special_rules' => [], 'victory' => [], 'defeat' => ['All dead.'], 'progression' => []]],
        'campaign' => ['victory' => '', 'defeat' => '', 'aftermath' => ['Draw a hand.'], 'next_encounter' => ''],
        'is_published' => false,
    ], $overrides);
}

it('gates the cooperative catalog behind the campaign catalog permissions', function () {
    $this->get(route('admin.cooperative.classes.index'))->assertRedirect();
    $nobody = coopAdmin([]);
    $this->actingAs($nobody)->get(route('admin.cooperative.classes.index'))->assertForbidden();
    $viewer = coopAdmin(['view_campaign_catalog']);
    $this->actingAs($viewer)->get(route('admin.cooperative.classes.index'))->assertOk();
    $this->actingAs($viewer)->get(route('admin.cooperative.encounters.index'))->assertOk();
    $this->actingAs($viewer)->post(route('admin.cooperative.classes.store'), classPayload())->assertForbidden();
});

it('creates, updates and deletes an NP class', function () {
    $admin = coopAdmin();

    $this->actingAs($admin)->post(route('admin.cooperative.classes.store'), classPayload())->assertRedirect(route('admin.cooperative.classes.index'));
    $class = CoopClass::firstOrFail();
    expect($class->role->value)->toBe('firepower')->and($class->behavior[0]['effects'])->toHaveCount(2);

    $this->actingAs($admin)->get(route('admin.cooperative.classes.edit', $class))->assertOk();
    $this->actingAs($admin)->post(route('admin.cooperative.classes.update', $class), classPayload(['max_health' => 7]))->assertRedirect();
    expect($class->fresh()->max_health)->toBe(7);

    $this->actingAs($admin)->post(route('admin.cooperative.classes.delete', $class))->assertRedirect();
    expect(CoopClass::count())->toBe(0);
});

it('rejects bad class data', function () {
    $admin = coopAdmin();
    CoopClass::factory()->create(['slug' => 'taken']);

    $this->actingAs($admin)->post(route('admin.cooperative.classes.store'), classPayload(['slug' => 'taken']))->assertSessionHasErrors('slug');
    $this->actingAs($admin)->post(route('admin.cooperative.classes.store'), classPayload(['role' => 'wizard']))->assertSessionHasErrors('role');
    $this->actingAs($admin)->post(route('admin.cooperative.classes.store'), classPayload(['behavior' => [['flip_min' => 9, 'flip_max' => 3, 'effects' => ['Walk']]]]))->assertSessionHasErrors('behavior.0.flip_max');
    $this->actingAs($admin)->post(route('admin.cooperative.classes.store'), classPayload(['behavior' => [['flip_min' => 1, 'effects' => []]]]))->assertSessionHasErrors('behavior.0.effects');
    expect(CoopClass::count())->toBe(1);
});

it('keeps a class slug when editing itself', function () {
    $admin = coopAdmin();
    $class = CoopClass::factory()->create(['slug' => 'mine']);

    $this->actingAs($admin)->post(route('admin.cooperative.classes.update', $class), classPayload(['slug' => 'mine']))->assertSessionHasNoErrors();
});

it('creates, updates and deletes an encounter and checks suggested classes exist', function () {
    $admin = coopAdmin();

    $this->actingAs($admin)->post(route('admin.cooperative.encounters.store'), encounterPayload())->assertRedirect(route('admin.cooperative.encounters.index'));
    $encounter = CoopEncounter::firstOrFail();
    expect($encounter->phases[0]['title'])->toBe('Opening')->and($encounter->campaign['aftermath'])->toBe(['Draw a hand.']);

    $this->actingAs($admin)->get(route('admin.cooperative.encounters.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.cooperative.encounters.edit', $encounter))->assertOk();
    $this->actingAs($admin)->post(route('admin.cooperative.encounters.update', $encounter), encounterPayload(['is_published' => true]))->assertRedirect();
    expect($encounter->fresh()->is_published)->toBeTrue();

    $bad = encounterPayload(['number' => 2, 'slug' => 'two', 'phases' => [['title' => 'A', 'role_order' => [['role' => 'brawler', 'class_slug' => 'ghost']]]]]);
    $this->actingAs($admin)->post(route('admin.cooperative.encounters.store'), $bad)->assertSessionHasErrors('phases.0.role_order.0.class_slug');
    $this->actingAs($admin)->post(route('admin.cooperative.encounters.store'), encounterPayload())->assertSessionHasErrors(['number', 'slug']);

    $this->actingAs($admin)->post(route('admin.cooperative.encounters.delete', $encounter))->assertRedirect();
    expect(CoopEncounter::count())->toBe(0);
});
