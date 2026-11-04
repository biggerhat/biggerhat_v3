<?php

use App\Models\CustomCardVersion;
use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

beforeEach(fn () => Bus::fake());

function ccvCharacter(User $user, array $overrides = []): CustomCharacter
{
    return CustomCharacter::create(array_merge(['user_id' => $user->id, 'name' => 'First', 'faction' => 'guild', 'health' => 8, 'base' => 30, 'defense' => 5, 'willpower' => 5, 'speed' => 5, 'actions' => [], 'abilities' => []], $overrides));
}

it('keeps the state each saved edit replaced, skipping no-op saves and capping the history', function () {
    $user = User::factory()->create();
    $card = ccvCharacter($user);

    $card->update(['notes' => 'not a card field']);
    expect($card->versions()->count())->toBe(0);

    $card->update(['name' => 'Second', 'actions' => [['name' => 'Slash']]]);
    $version = $card->versions()->first();
    expect($version->snapshot['name'])->toBe('First')->and($version->snapshot['actions'])->toBe([]);

    for ($i = 0; $i < 15; $i++) {
        $card->update(['name' => "Edit {$i}"]);
    }
    expect($card->versions()->count())->toBe(10);
});

it('does not keep history for campaign-managed cards', function () {
    $user = User::factory()->create();
    $leader = ccvCharacter($user, ['is_campaign_leader' => true]);
    $leader->update(['name' => 'Changed']);

    $crewCard = CustomUpgrade::create(['user_id' => $user->id, 'name' => 'Crew', 'domain' => 'crew', 'content_blocks' => [], 'is_campaign_crew_card' => true]);
    $crewCard->update(['name' => 'Changed']);

    expect(CustomCardVersion::count())->toBe(0);
});

it('restores an earlier version for the owner only, and the restore is itself undoable', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $card = ccvCharacter($owner);
    $card->update(['name' => 'Second']);
    $version = $card->versions()->first();
    $url = route('tools.card_creator.restore_version', [$card->id, $version->id]);

    $this->actingAs($other)->postJson($url)->assertForbidden();

    $this->actingAs($owner)->postJson($url)->assertOk()->assertJsonPath('redirect', route('tools.card_creator.edit', $card->id));
    expect($card->fresh()->name)->toBe('First')->and($card->versions()->count())->toBe(2);

    // A version that belongs to another card can't be applied here.
    $otherCard = ccvCharacter($owner, ['name' => 'Other']);
    $otherCard->update(['name' => 'Other 2']);
    $this->actingAs($owner)->postJson(route('tools.card_creator.restore_version', [$card->id, $otherCard->versions()->first()->id]))->assertNotFound();
});

it('lists versions in the editor payload and restores upgrades the same way', function () {
    $owner = User::factory()->create();
    $card = ccvCharacter($owner);
    $card->update(['name' => 'Second']);

    $this->actingAs($owner)->get(route('tools.card_creator.edit', $card->id))
        ->assertInertia(fn ($page) => $page->has('versions', 1)->where('versions.0.label', 'First · 0 actions · 0 abilities'));

    $up = CustomUpgrade::create(['user_id' => $owner->id, 'name' => 'Up A', 'domain' => 'character', 'content_blocks' => []]);
    $up->update(['name' => 'Up B', 'content_blocks' => [['type' => 'text', 'text' => 'x']]]);
    $this->actingAs($owner)->postJson(route('tools.card_creator.upgrades.restore_version', [$up->id, $up->versions()->first()->id]))->assertOk();
    expect($up->fresh()->name)->toBe('Up A')->and($up->fresh()->content_blocks)->toBe([]);
});

it('removes a deleted card\'s history', function () {
    $user = User::factory()->create();
    $card = ccvCharacter($user);
    $card->update(['name' => 'Second']);
    expect(CustomCardVersion::count())->toBe(1);

    $card->delete();
    expect(CustomCardVersion::count())->toBe(0);
});
