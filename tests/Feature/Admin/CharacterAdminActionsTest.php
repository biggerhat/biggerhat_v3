<?php

use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\Action;
use App\Models\Character;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Regression guard #1: the admin Actions multiselect used to submit a
 * composite "{id} {name} {internal_notes}" string as its value (parsed
 * server-side via explode(' ', $x)[0]) purely so the label could show the
 * action's id for data-entry disambiguation (Action.name is not unique).
 * This relied on fragile string parsing. It's now a plain numeric id
 * end-to-end, with the id shown via the label text instead ("Name (#123)").
 *
 * Regression guard #2 (found while writing the above): the "actions" and
 * "signature actions" multiselects are two independent selections over the
 * same catalog, so the same action id commonly appears in both (a signature
 * action is still one of the character's actions). The controller used to
 * attach() both collections separately, inserting a duplicate pivot row for
 * any such overlap (there's no unique constraint on the characterables pivot
 * to catch it) — silently double-listing that action wherever the
 * relation is displayed. Fixed by merging both into one sync() map.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all()));
});

function baseCharacterPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Character',
        'faction' => FactionEnum::Guild->value,
        'station' => CharacterStationEnum::Master->value,
        'health' => 8,
        'size' => 2,
        'base' => BaseSizeEnum::ThirtyMM->value,
        'speed' => 5,
        'count' => 1,
        'defense' => 5,
        'willpower' => 5,
        'generates_stone' => false,
        'is_unhirable' => false,
        'is_beta' => false,
        'game_mode_type' => GameModeTypeEnum::Standard->value,
        'is_hidden' => false,
        'summon_target_number' => null,
        'totem' => null,
        'keywords' => [],
        'characteristics' => [],
        'abilities' => [],
        'markers' => [],
        'tokens' => [],
    ], $overrides);
}

it('exposes the action id in the admin actions options label', function () {
    $action = Action::factory()->create(['name' => 'Cast Fireball']);

    $response = $this->actingAs($this->admin)->get(route('admin.characters.create'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('actions', fn ($actions) => collect($actions)->contains(
                fn ($a) => $a['id'] === $action->id && str_contains($a['name'], "(#{$action->id})")
            ))
        );
});

it('links actions and signature actions by plain numeric id', function () {
    $a1 = Action::factory()->create();
    $a2 = Action::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.characters.store'), baseCharacterPayload([
            'actions' => [$a1->id, $a2->id],
            'signature_actions' => [$a2->id],
        ]))
        ->assertRedirect(route('admin.characters.index'));

    $character = Character::where('name', 'Test Character')->first();
    // a2 is in both lists — assert exactly 2 rows (no duplicate pivot row).
    expect($character->actions()->count())->toBe(2);
    expect($character->actions()->pluck('actions.id')->all())->toEqualCanonicalizing([$a1->id, $a2->id]);
    expect($character->actions()->wherePivot('is_signature_action', true)->pluck('actions.id')->all())->toEqual([$a2->id]);
});
