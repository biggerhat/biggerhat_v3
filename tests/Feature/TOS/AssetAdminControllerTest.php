<?php

use App\Enums\PermissionEnum;
use App\Enums\TOS\AssetLimitTypeEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Unit;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([PermissionEnum::ViewTosAsset, PermissionEnum::EditTosAsset, PermissionEnum::DeleteTosAsset] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $role->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');

    $this->stranger = User::factory()->create();
});

it('index denies users without view_tos_asset', function () {
    $this->actingAs($this->stranger)->get(route('admin.tos.assets.index'))->assertForbidden();
});

it('admin store persists multiple limit rows at once', function () {
    $ke = Allegiance::factory()->create();
    $earl = Unit::factory()->withSides()->commander()->create(['slug' => 'earl']);

    $this->actingAs($this->admin)->post(route('admin.tos.assets.store'), [
        'name' => 'Royal Warhorn',
        'scrip_cost' => 3,
        'allegiance_ids' => [$ke->id],
        'limits' => [
            [
                'limit_type' => AssetLimitTypeEnum::Restricted->value,
                'parameter_type' => 'unit_name',
                'parameter_value' => $earl->slug,
                'parameter_unit_id' => $earl->id,
            ],
            [
                'limit_type' => AssetLimitTypeEnum::Slot->value,
                'parameter_type' => 'location',
                'parameter_value' => 'Horn',
            ],
        ],
    ])->assertRedirect(route('admin.tos.assets.index'));

    $asset = Asset::where('name', 'Royal Warhorn')->first();
    expect($asset)->not->toBeNull()
        ->and($asset->limits()->count())->toBe(2)
        ->and($asset->limits->pluck('limit_type.value')->all())->toEqualCanonicalizing([
            AssetLimitTypeEnum::Restricted->value,
            AssetLimitTypeEnum::Slot->value,
        ])
        ->and($asset->allegiances->pluck('id'))->toContain($ke->id);
});

it('admin store rejects an invalid limit_type enum value', function () {
    $this->actingAs($this->admin)->postJson(route('admin.tos.assets.store'), [
        'name' => 'Bad',
        'scrip_cost' => 1,
        'limits' => [['limit_type' => 'not-a-type']],
    ])->assertStatus(422);
});

it('admin update re-syncs limits and pivots (old limits replaced)', function () {
    $asset = Asset::factory()->unique()->create();
    expect($asset->limits()->count())->toBe(1);

    $this->actingAs($this->admin)->post(route('admin.tos.assets.update', $asset->slug), [
        'name' => $asset->name,
        'scrip_cost' => $asset->scrip_cost,
        'limits' => [
            ['limit_type' => AssetLimitTypeEnum::Slot->value, 'parameter_type' => 'location', 'parameter_value' => 'Head'],
        ],
    ])->assertRedirect();

    $asset->refresh();
    expect($asset->limits()->count())->toBe(1)
        ->and($asset->limits->first()->limit_type)->toBe(AssetLimitTypeEnum::Slot);
});

it('admin delete cascades limits and pivots', function () {
    $asset = Asset::factory()->forAllegiance(Allegiance::factory()->create())->unique()->create();
    $assetId = $asset->id;

    $this->actingAs($this->admin)->post(route('admin.tos.assets.delete', $asset->slug))->assertRedirect();

    expect(Asset::find($assetId))->toBeNull()
        ->and(\DB::table('tos_asset_limits')->where('asset_id', $assetId)->count())->toBe(0)
        ->and(\DB::table('tos_allegiance_asset')->where('asset_id', $assetId)->count())->toBe(0);
});

it('admin store denies users without edit_tos_asset', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosAsset->value);

    $this->actingAs($viewer)->post(route('admin.tos.assets.store'), [
        'name' => 'Blocked',
        'scrip_cost' => 1,
    ])->assertForbidden();

    expect(Asset::where('name', 'Blocked')->exists())->toBeFalse();
});

it('admin update denies users without edit_tos_asset', function () {
    $asset = Asset::factory()->create(['name' => 'Original']);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosAsset->value);

    $this->actingAs($viewer)->post(route('admin.tos.assets.update', $asset->slug), [
        'name' => 'Hijacked',
        'scrip_cost' => 5,
    ])->assertForbidden();

    expect($asset->fresh()->name)->toBe('Original');
});
