<?php

use App\Enums\FactionEnum;
use App\Models\CardArt;
use App\Models\CustomCharacter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function artSuperAdmin(): User
{
    Role::firstOrCreate(['name' => 'super_admin']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

beforeEach(fn () => Storage::fake('public'));

it('blocks non-super-admins from every card art route', function () {
    $user = User::factory()->create();
    $art = CardArt::factory()->create();

    $this->actingAs($user)->get(route('admin.card_arts.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.card_arts.create'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.card_arts.store'), [])->assertForbidden();
    $this->actingAs($user)->post(route('admin.card_arts.update', $art), [])->assertForbidden();
    $this->actingAs($user)->post(route('admin.card_arts.delete', $art))->assertForbidden();
    expect(CardArt::count())->toBe(1);
});

it('lists the library with how many cards use each piece', function () {
    $admin = artSuperAdmin();
    $art = CardArt::factory()->create(['name' => 'Gunslinger']);
    CustomCharacter::create([
        'user_id' => $admin->id, 'name' => 'Uses It', 'faction' => 'guild', 'health' => 10, 'base' => 30,
        'defense' => 5, 'willpower' => 5, 'speed' => 5, 'card_art_id' => $art->id,
    ]);

    $this->actingAs($admin)->get(route('admin.card_arts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/CardArts/Index')
            ->has('arts', 1)
            ->where('arts.0.name', 'Gunslinger')
            ->where('arts.0.in_use', 1));
});

it('stores an uploaded image under a unique slug', function () {
    $admin = artSuperAdmin();
    $payload = fn (string $name) => [
        'name' => $name, 'faction' => FactionEnum::Guild->value, 'category' => 'Human', 'is_active' => 1, 'sort_order' => 3,
        'image' => UploadedFile::fake()->image('art.png', 400, 300),
    ];

    $this->actingAs($admin)->post(route('admin.card_arts.store'), $payload('Hooded Gunslinger'))->assertRedirect(route('admin.card_arts.index'));
    $this->actingAs($admin)->post(route('admin.card_arts.store'), $payload('Hooded Gunslinger'))->assertRedirect();

    $arts = CardArt::orderBy('id')->get();
    expect($arts)->toHaveCount(2)
        ->and($arts[0]->slug)->toBe('hooded-gunslinger')
        ->and($arts[1]->slug)->toBe('hooded-gunslinger-2')
        ->and($arts[0]->faction)->toBe(FactionEnum::Guild)
        ->and($arts[0]->sort_order)->toBe(3);
    Storage::disk('public')->assertExists($arts[0]->image_path);
});

it('rejects a missing image, a non-image file, an oversized file and an unknown faction', function () {
    $admin = artSuperAdmin();

    $this->actingAs($admin)->post(route('admin.card_arts.store'), ['name' => 'No image'])->assertSessionHasErrors('image');
    $this->actingAs($admin)->post(route('admin.card_arts.store'), ['name' => 'Doc', 'image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertSessionHasErrors('image');
    $this->actingAs($admin)->post(route('admin.card_arts.store'), ['name' => 'Big', 'image' => UploadedFile::fake()->image('big.png')->size(6000)])->assertSessionHasErrors('image');
    $this->actingAs($admin)->post(route('admin.card_arts.store'), ['name' => 'Bad faction', 'faction' => 'nope', 'image' => UploadedFile::fake()->image('a.png')])->assertSessionHasErrors('faction');
    $this->actingAs($admin)->post(route('admin.card_arts.store'), ['name' => '', 'image' => UploadedFile::fake()->image('a.png')])->assertSessionHasErrors('name');
    expect(CardArt::count())->toBe(0);
});

it('updates fields without a new image and replaces the file when one is sent', function () {
    $admin = artSuperAdmin();
    $this->actingAs($admin)->post(route('admin.card_arts.store'), ['name' => 'Original', 'image' => UploadedFile::fake()->image('a.png')]);
    $art = CardArt::firstOrFail();
    $oldPath = $art->image_path;

    $this->actingAs($admin)->post(route('admin.card_arts.update', $art), ['name' => 'Renamed', 'category' => 'Beast', 'is_active' => 0])->assertRedirect(route('admin.card_arts.index'));
    $art->refresh();
    expect($art->name)->toBe('Renamed')->and($art->category)->toBe('Beast')->and($art->is_active)->toBeFalse()->and($art->image_path)->toBe($oldPath);

    $this->actingAs($admin)->post(route('admin.card_arts.update', $art), ['name' => 'Renamed', 'is_active' => 1, 'image' => UploadedFile::fake()->image('b.jpg')]);
    $art->refresh();
    expect($art->image_path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($art->image_path);
});

it('deleting art removes the file and clears it from the cards that used it', function () {
    $admin = artSuperAdmin();
    $this->actingAs($admin)->post(route('admin.card_arts.store'), ['name' => 'Doomed', 'image' => UploadedFile::fake()->image('a.png')]);
    $art = CardArt::firstOrFail();
    $character = CustomCharacter::create([
        'user_id' => $admin->id, 'name' => 'Holder', 'faction' => 'guild', 'health' => 10, 'base' => 30,
        'defense' => 5, 'willpower' => 5, 'speed' => 5, 'card_art_id' => $art->id,
    ]);
    $path = $art->image_path;

    $this->actingAs($admin)->post(route('admin.card_arts.delete', $art))->assertRedirect(route('admin.card_arts.index'));

    expect(CardArt::count())->toBe(0)->and($character->fresh()->card_art_id)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('renders the create and edit pages', function () {
    $admin = artSuperAdmin();
    $art = CardArt::factory()->create();

    $this->actingAs($admin)->get(route('admin.card_arts.create'))->assertOk()->assertInertia(fn ($p) => $p->component('Admin/CardArts/Form')->where('art', null));
    $this->actingAs($admin)->get(route('admin.card_arts.edit', $art))->assertOk()->assertInertia(fn ($p) => $p->where('art.id', $art->id));
});
