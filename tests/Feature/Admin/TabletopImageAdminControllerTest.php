<?php

use App\Models\TabletopImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function tabletopAdmin(): User
{
    Role::firstOrCreate(['name' => 'super_admin']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

beforeEach(fn () => Storage::fake('public'));

it('blocks non-super-admins from every tabletop image route', function () {
    $user = User::factory()->create();
    $image = TabletopImage::factory()->create();

    $this->actingAs($user)->get(route('admin.tabletop_images.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.tabletop_images.create'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.tabletop_images.store'), [])->assertForbidden();
    $this->actingAs($user)->get(route('admin.tabletop_images.edit', $image))->assertForbidden();
    $this->actingAs($user)->post(route('admin.tabletop_images.update', $image), [])->assertForbidden();
    $this->actingAs($user)->post(route('admin.tabletop_images.delete', $image))->assertForbidden();
    expect(TabletopImage::count())->toBe(1);
});

it('lists what was added and the built-in images', function () {
    TabletopImage::factory()->create(['name' => 'Marble floor']);
    TabletopImage::factory()->terrain('tower')->create(['name' => 'Granite tower']);

    $this->actingAs(tabletopAdmin())->get(route('admin.tabletop_images.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/TabletopImages/Index')
            ->has('images', 2)
            ->where('images.0.name', 'Marble floor')
            ->where('images.1.terrain_label', 'Tower')
            ->has('defaults.tables', 6)
            ->has('defaults.terrain', 12));
});

it('adds a table surface', function () {
    $this->actingAs(tabletopAdmin())->post(route('admin.tabletop_images.store'), [
        'name' => 'Marble floor', 'kind' => 'table', 'is_active' => 1, 'image' => UploadedFile::fake()->image('marble.png', 64, 64),
    ])->assertRedirect(route('admin.tabletop_images.index'));

    $image = TabletopImage::firstOrFail();
    expect($image->kind)->toBe('table')->and($image->terrain_key)->toBeNull();
    Storage::disk('public')->assertExists($image->image_path);
});

it('adds a terrain texture for one kind of terrain, and drops a terrain key sent with a table surface', function () {
    $admin = tabletopAdmin();
    $this->actingAs($admin)->post(route('admin.tabletop_images.store'), [
        'name' => 'Granite', 'kind' => 'terrain', 'terrain_key' => 'tower', 'image' => UploadedFile::fake()->image('g.webp', 32, 32),
    ])->assertRedirect();
    $this->actingAs($admin)->post(route('admin.tabletop_images.store'), [
        'name' => 'Marble', 'kind' => 'table', 'terrain_key' => 'tower', 'image' => UploadedFile::fake()->image('m.png', 32, 32),
    ])->assertRedirect();

    expect(TabletopImage::where('name', 'Granite')->value('terrain_key'))->toBe('tower')
        ->and(TabletopImage::where('name', 'Marble')->value('terrain_key'))->toBeNull();
});

it('refuses a bad image, kind or terrain key', function () {
    $admin = tabletopAdmin();
    $good = fn (array $over = []) => array_merge(['name' => 'X', 'kind' => 'table', 'image' => UploadedFile::fake()->image('x.png', 32, 32)], $over);

    $this->actingAs($admin)->post(route('admin.tabletop_images.store'), $good(['image' => null]))->assertSessionHasErrors('image');
    $this->actingAs($admin)->post(route('admin.tabletop_images.store'), $good(['image' => UploadedFile::fake()->create('x.svg', 4, 'image/svg+xml')]))->assertSessionHasErrors('image');
    $this->actingAs($admin)->post(route('admin.tabletop_images.store'), $good(['image' => UploadedFile::fake()->create('big.png', 6000, 'image/png')]))->assertSessionHasErrors('image');
    $this->actingAs($admin)->post(route('admin.tabletop_images.store'), $good(['kind' => 'floor']))->assertSessionHasErrors('kind');
    $this->actingAs($admin)->post(route('admin.tabletop_images.store'), $good(['kind' => 'terrain']))->assertSessionHasErrors('terrain_key');
    $this->actingAs($admin)->post(route('admin.tabletop_images.store'), $good(['kind' => 'terrain', 'terrain_key' => 'spaceship']))->assertSessionHasErrors('terrain_key');
    $this->actingAs($admin)->post(route('admin.tabletop_images.store'), $good(['name' => '']))->assertSessionHasErrors('name');
    expect(TabletopImage::count())->toBe(0);
});

it('edits an image, replacing the file only when a new one is sent', function () {
    $admin = tabletopAdmin();
    Storage::disk('public')->put('tabletop/old.webp', 'x');
    $image = TabletopImage::factory()->create(['name' => 'Old', 'image_path' => 'tabletop/old.webp']);

    $this->actingAs($admin)->post(route('admin.tabletop_images.update', $image), ['name' => 'Renamed', 'kind' => 'table', 'is_active' => 0])->assertRedirect();
    expect($image->fresh()->name)->toBe('Renamed')->and($image->fresh()->is_active)->toBeFalse()->and($image->fresh()->image_path)->toBe('tabletop/old.webp');
    Storage::disk('public')->assertExists('tabletop/old.webp');

    $this->actingAs($admin)->post(route('admin.tabletop_images.update', $image), [
        'name' => 'Renamed', 'kind' => 'table', 'is_active' => 1, 'image' => UploadedFile::fake()->image('new.png', 32, 32),
    ])->assertRedirect();
    Storage::disk('public')->assertMissing('tabletop/old.webp');
    Storage::disk('public')->assertExists($image->fresh()->image_path);
});

it('deletes an image and its file', function () {
    Storage::disk('public')->put('tabletop/gone.webp', 'x');
    $image = TabletopImage::factory()->create(['image_path' => 'tabletop/gone.webp']);

    $this->actingAs(tabletopAdmin())->post(route('admin.tabletop_images.delete', $image))->assertRedirect(route('admin.tabletop_images.index'));

    expect(TabletopImage::count())->toBe(0);
    Storage::disk('public')->assertMissing('tabletop/gone.webp');
});
