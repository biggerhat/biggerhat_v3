<?php

use App\Enums\PermissionEnum;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([PermissionEnum::ViewTosSculpt, PermissionEnum::EditTosSculpt, PermissionEnum::DeleteTosSculpt] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $role->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');

    $this->stranger = User::factory()->create();

    Storage::fake('public');
});

it('index denies users without view_tos_sculpt', function () {
    $this->actingAs($this->stranger)->get(route('admin.tos.sculpts.index'))->assertForbidden();
});

it('store creates a Sculpt without images', function () {
    $unit = Unit::factory()->withSides()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.sculpts.store'), [
        'unit_id' => $unit->id,
        'name' => 'Base Sculpt',
    ])->assertRedirect(route('admin.tos.sculpts.index'));

    $sculpt = UnitSculpt::where('name', 'Base Sculpt')->first();
    expect($sculpt)->not->toBeNull()
        ->and($sculpt->unit_id)->toBe($unit->id)
        ->and($sculpt->front_image)->toBeNull()
        ->and($sculpt->back_image)->toBeNull()
        ->and($sculpt->combination_image)->toBeNull();
});

it('store with front+back generates a combination image', function () {
    $unit = Unit::factory()->withSides()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.sculpts.store'), [
        'unit_id' => $unit->id,
        'name' => 'Dual Sculpt',
        'front_image' => UploadedFile::fake()->image('front.jpg', 300, 400)->mimeType('image/jpeg'),
        'back_image' => UploadedFile::fake()->image('back.jpg', 300, 400)->mimeType('image/jpeg'),
    ])->assertRedirect();

    $sculpt = UnitSculpt::where('name', 'Dual Sculpt')->first();
    expect($sculpt->front_image)->not->toBeNull()
        ->and($sculpt->back_image)->not->toBeNull()
        ->and($sculpt->combination_image)->not->toBeNull()
        ->and($sculpt->combination_image)->not->toBe($sculpt->front_image);

    Storage::disk('public')->assertExists($sculpt->front_image);
    Storage::disk('public')->assertExists($sculpt->back_image);
    Storage::disk('public')->assertExists($sculpt->combination_image);
});

it('store with only front sets combination_image to the front image', function () {
    $unit = Unit::factory()->withSides()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.sculpts.store'), [
        'unit_id' => $unit->id,
        'name' => 'Front Only',
        'front_image' => UploadedFile::fake()->image('front.jpg', 300, 400)->mimeType('image/jpeg'),
    ])->assertRedirect();

    $sculpt = UnitSculpt::where('name', 'Front Only')->first();
    expect($sculpt->front_image)->not->toBeNull()
        ->and($sculpt->back_image)->toBeNull()
        ->and($sculpt->combination_image)->toBe($sculpt->front_image);
});

it('update replaces a front image and regenerates the combo', function () {
    $unit = Unit::factory()->withSides()->create();
    $sculpt = UnitSculpt::factory()->forUnit($unit)->create();

    // Seed it with images via the store endpoint so combo exists.
    $this->actingAs($this->admin)->post(route('admin.tos.sculpts.update', $sculpt->slug), [
        'unit_id' => $unit->id,
        'name' => $sculpt->name,
        'front_image' => UploadedFile::fake()->image('front.jpg', 300, 400)->mimeType('image/jpeg'),
        'back_image' => UploadedFile::fake()->image('back.jpg', 300, 400)->mimeType('image/jpeg'),
    ])->assertRedirect();

    $sculpt->refresh();
    $originalCombo = $sculpt->combination_image;
    expect($originalCombo)->not->toBeNull();

    // Now swap the front image — combo should be regenerated.
    $this->actingAs($this->admin)->post(route('admin.tos.sculpts.update', $sculpt->slug), [
        'unit_id' => $unit->id,
        'name' => $sculpt->name,
        'front_image' => UploadedFile::fake()->image('new-front.jpg', 300, 400)->mimeType('image/jpeg'),
    ])->assertRedirect();

    $sculpt->refresh();
    expect($sculpt->combination_image)->not->toBe($originalCombo);
    Storage::disk('public')->assertMissing($originalCombo);
});

it('delete removes the sculpt and its images', function () {
    $unit = Unit::factory()->withSides()->create();
    $sculpt = UnitSculpt::factory()->forUnit($unit)->create();

    $this->actingAs($this->admin)->post(route('admin.tos.sculpts.update', $sculpt->slug), [
        'unit_id' => $unit->id,
        'name' => $sculpt->name,
        'front_image' => UploadedFile::fake()->image('front.jpg', 300, 400)->mimeType('image/jpeg'),
        'back_image' => UploadedFile::fake()->image('back.jpg', 300, 400)->mimeType('image/jpeg'),
    ])->assertRedirect();

    $sculpt->refresh();
    $front = $sculpt->front_image;
    $back = $sculpt->back_image;
    $combo = $sculpt->combination_image;

    $this->actingAs($this->admin)->post(route('admin.tos.sculpts.delete', $sculpt->slug))->assertRedirect();

    expect(UnitSculpt::find($sculpt->id))->toBeNull();
    Storage::disk('public')->assertMissing($front);
    Storage::disk('public')->assertMissing($back);
    Storage::disk('public')->assertMissing($combo);
});

it('store denies users without edit_tos_sculpt', function () {
    $unit = Unit::factory()->withSides()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosSculpt->value);

    $this->actingAs($viewer)->post(route('admin.tos.sculpts.store'), [
        'unit_id' => $unit->id,
        'name' => 'Blocked',
    ])->assertForbidden();

    expect(UnitSculpt::where('name', 'Blocked')->exists())->toBeFalse();
});
