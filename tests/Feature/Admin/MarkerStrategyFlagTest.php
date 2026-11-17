<?php

use App\Enums\BaseSizeEnum;
use App\Models\Marker;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function markerAdmin(): User
{
    $user = User::factory()->create();
    foreach (['view_marker', 'edit_marker'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $user->givePermissionTo($name);
    }

    return $user;
}

it('saves whether a marker is a Strategy marker when it is created and when it is changed', function () {
    $admin = markerAdmin();

    $this->actingAs($admin)->post(route('admin.markers.store'), ['name' => 'Turf Marker', 'base' => BaseSizeEnum::ThirtyMM->value, 'is_strategy' => true])->assertRedirect();
    $marker = Marker::where('name', 'Turf Marker')->firstOrFail();
    expect($marker->is_strategy)->toBeTrue();

    $this->actingAs($admin)->post(route('admin.markers.update', $marker), ['name' => 'Turf Marker', 'base' => BaseSizeEnum::ThirtyMM->value, 'is_strategy' => false])->assertRedirect();
    expect($marker->fresh()->is_strategy)->toBeFalse();
});

it('is not a Strategy marker unless it is said to be, and refuses a non-boolean flag', function () {
    $admin = markerAdmin();

    $this->actingAs($admin)->post(route('admin.markers.store'), ['name' => 'Scheme Marker', 'base' => BaseSizeEnum::ThirtyMM->value])->assertRedirect();
    expect(Marker::where('name', 'Scheme Marker')->firstOrFail()->is_strategy)->toBeFalse();

    $this->actingAs($admin)->post(route('admin.markers.store'), ['name' => 'Odd Marker', 'base' => BaseSizeEnum::ThirtyMM->value, 'is_strategy' => 'perhaps'])->assertSessionHasErrors('is_strategy');
});
