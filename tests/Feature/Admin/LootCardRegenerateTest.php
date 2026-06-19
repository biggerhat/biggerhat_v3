<?php

use App\Enums\PermissionEnum;
use App\Models\LootCard;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('renders the regenerate page with full card data', function () {
    LootCard::create(['slug' => 'a', 'name' => 'Card A', 'suit' => 'crow', 'value' => 1, 'value_label' => '1', 'sort_order' => 1]);

    $this->actingAs($this->admin)
        ->get(route('admin.loot_cards.regenerate'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/LootCards/Regenerate')->has('cards', 1));
});

it('stores a regenerated image for a card', function () {
    Storage::fake('public');
    $card = LootCard::create(['slug' => 'b', 'name' => 'Card B', 'suit' => 'ram', 'value' => 2, 'value_label' => '2', 'sort_order' => 1]);

    $this->actingAs($this->admin)
        ->post(route('admin.loot_cards.image', $card), ['image' => UploadedFile::fake()->image('b.png')])
        ->assertOk()
        ->assertJsonStructure(['image']);

    $card->refresh();
    expect($card->image)->not->toBeNull();
    Storage::disk('public')->assertExists($card->image);
});

it('blocks non-super-admins from the regenerate tooling', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.loot_cards.regenerate'))->assertForbidden();
});
