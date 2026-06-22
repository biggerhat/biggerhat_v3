<?php

use App\Enums\PermissionEnum;
use App\Jobs\GenerateBonanzaLootDeckPdf;
use App\Models\LootCard;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
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

it('queues a PDF regeneration from the manual endpoint', function () {
    Bus::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.loot_cards.generate_pdf'))
        ->assertRedirect(route('admin.loot_cards.index'));

    Bus::assertDispatched(GenerateBonanzaLootDeckPdf::class);
});

it('queues a PDF regeneration when a card is updated', function () {
    Bus::fake();
    $card = LootCard::create(['slug' => 'c', 'name' => 'C', 'suit' => 'crow', 'value' => 1, 'value_label' => '1', 'sort_order' => 1]);

    $this->actingAs($this->admin)
        ->post(route('admin.loot_cards.update', $card->slug), ['name' => 'C2', 'suit' => 'crow', 'value' => 1])
        ->assertRedirect();

    Bus::assertDispatched(GenerateBonanzaLootDeckPdf::class);
});

it('queues a PDF regeneration when a card is deleted', function () {
    Bus::fake();
    $card = LootCard::create(['slug' => 'd', 'name' => 'D', 'suit' => 'ram', 'value' => 2, 'value_label' => '2', 'sort_order' => 1]);

    $this->actingAs($this->admin)
        ->delete(route('admin.loot_cards.destroy', $card->slug))
        ->assertRedirect(route('admin.loot_cards.index'));

    Bus::assertDispatched(GenerateBonanzaLootDeckPdf::class);
});

it('is unique so a burst of edits collapses to one render', function () {
    $job = new GenerateBonanzaLootDeckPdf;
    expect($job->uniqueId)->toBe('bonanza-deck-pdf');
});
