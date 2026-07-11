<?php

use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use App\Models\User;

function cuValidPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Upgrade',
        'domain' => 'character',
        'faction' => 'guild',
        'content_blocks' => [],
    ], $overrides);
}

it('index only lists the current user\'s own upgrades', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    CustomUpgrade::create(array_merge(cuValidPayload(['name' => 'Mine']), ['user_id' => $user->id]));
    CustomUpgrade::create(array_merge(cuValidPayload(['name' => 'Not Mine']), ['user_id' => $other->id]));

    $this->actingAs($user)
        ->get(route('tools.card_creator.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('upgrades', 1)->where('upgrades.0.name', 'Mine'));
});

it('create defaults to the character domain and falls back on an invalid one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tools.card_creator.upgrades.create', ['domain' => 'not-a-real-domain']))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('domain', 'character'));
});

it('requires auth to store an upgrade', function () {
    $this->postJson(route('tools.card_creator.upgrades.store'), cuValidPayload())->assertUnauthorized();
});

it('stores an upgrade and redirects to its edit page', function () {
    $user = User::factory()->create();

    $resp = $this->actingAs($user)->postJson(route('tools.card_creator.upgrades.store'), cuValidPayload());

    $resp->assertOk()->assertJson(['success' => true]);
    $upgrade = CustomUpgrade::where('user_id', $user->id)->firstOrFail();
    expect($resp->json('redirect'))->toBe(route('tools.card_creator.upgrades.edit', $upgrade->id));
    expect($upgrade->faction->value)->toBe('guild');
});

it('rejects store with an invalid faction — regression for the enum-cast crash', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tools.card_creator.upgrades.store'), cuValidPayload(['faction' => 'not_a_real_faction']))
        ->assertJsonValidationErrors('faction');

    expect(CustomUpgrade::where('user_id', $user->id)->exists())->toBeFalse();
});

it('rejects store with an invalid domain', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tools.card_creator.upgrades.store'), cuValidPayload(['domain' => 'not-crew-or-character']))
        ->assertJsonValidationErrors('domain');
});

it('rejects store with an oversized content-block stone_cost — regression for the render-hang bug', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tools.card_creator.upgrades.store'), cuValidPayload([
            'content_blocks' => [['type' => 'action', 'data' => ['name' => 'Zap', 'stone_cost' => 999999]]],
        ]))
        ->assertJsonValidationErrors('content_blocks.0.data.stone_cost');
});

it('rejects store with an oversized nested content-block trigger stone_cost', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tools.card_creator.upgrades.store'), cuValidPayload([
            'content_blocks' => [[
                'type' => 'action',
                'data' => ['name' => 'Zap', 'triggers' => [['name' => 'Bad Trigger', 'stone_cost' => 500]]],
            ]],
        ]))
        ->assertJsonValidationErrors('content_blocks.0.data.triggers.0.stone_cost');
});

it('rejects store with an invalid content-block type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tools.card_creator.upgrades.store'), cuValidPayload([
            'content_blocks' => [['type' => 'not-a-real-type']],
        ]))
        ->assertJsonValidationErrors('content_blocks.0.type');
});

it('rejects store missing required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tools.card_creator.upgrades.store'), [])
        ->assertJsonValidationErrors(['name', 'domain']);
});

it('lets the owner view the edit page', function () {
    $user = User::factory()->create();
    $upgrade = CustomUpgrade::create(array_merge(cuValidPayload(), ['user_id' => $user->id]));

    $this->actingAs($user)
        ->get(route('tools.card_creator.upgrades.edit', $upgrade->id))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('upgrade.id', $upgrade->id));
});

it('blocks a non-owner from the edit page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $upgrade = CustomUpgrade::create(array_merge(cuValidPayload(), ['user_id' => $owner->id]));

    $this->actingAs($other)
        ->get(route('tools.card_creator.upgrades.edit', $upgrade->id))
        ->assertForbidden();
});

it('lets the owner update their upgrade', function () {
    $user = User::factory()->create();
    $upgrade = CustomUpgrade::create(array_merge(cuValidPayload(), ['user_id' => $user->id]));

    $this->actingAs($user)
        ->putJson(route('tools.card_creator.upgrades.update', $upgrade->id), cuValidPayload(['name' => 'Renamed']))
        ->assertOk()->assertJson(['success' => true]);

    expect($upgrade->fresh()->name)->toBe('Renamed');
});

it('blocks a non-owner from updating an upgrade', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $upgrade = CustomUpgrade::create(array_merge(cuValidPayload(), ['user_id' => $owner->id]));

    $this->actingAs($other)
        ->putJson(route('tools.card_creator.upgrades.update', $upgrade->id), cuValidPayload(['name' => 'Hijacked']))
        ->assertForbidden();

    expect($upgrade->fresh()->name)->not->toBe('Hijacked');
});

it('lets the owner delete their upgrade', function () {
    $user = User::factory()->create();
    $upgrade = CustomUpgrade::create(array_merge(cuValidPayload(), ['user_id' => $user->id]));

    $this->actingAs($user)
        ->deleteJson(route('tools.card_creator.upgrades.destroy', $upgrade->id))
        ->assertOk()->assertJson(['success' => true]);

    expect(CustomUpgrade::find($upgrade->id))->toBeNull();
});

it('blocks a non-owner from deleting an upgrade', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $upgrade = CustomUpgrade::create(array_merge(cuValidPayload(), ['user_id' => $owner->id]));

    $this->actingAs($other)
        ->deleteJson(route('tools.card_creator.upgrades.destroy', $upgrade->id))
        ->assertForbidden();

    expect(CustomUpgrade::find($upgrade->id))->not->toBeNull();
});

it('serves the public share page without auth, regardless of is_public', function () {
    $user = User::factory()->create();
    $upgrade = CustomUpgrade::create(array_merge(cuValidPayload(), ['user_id' => $user->id, 'is_public' => false]));

    $this->get(route('tools.card_creator.upgrades.share', $upgrade->share_code))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('upgrade.id', $upgrade->id)->where('creator_name', $user->name));
});

it('returns 404 for an unknown share code', function () {
    $this->get(route('tools.card_creator.upgrades.share', 'does-not-exist'))->assertNotFound();
});

it('does not surface the requesting user\'s custom characters or crew upgrades belonging to someone else in search', function () {
    $owner = User::factory()->create();
    $searcher = User::factory()->create();
    CustomCharacter::create(array_merge(
        ['name' => 'Owners Secret Character', 'faction' => 'guild', 'health' => 10, 'base' => 30, 'defense' => 5, 'willpower' => 5, 'speed' => 5],
        ['user_id' => $owner->id],
    ));

    $this->actingAs($searcher)
        ->getJson(route('api.card-creator.characters', ['q' => 'Secret']))
        ->assertOk()
        ->assertJsonCount(0);
});
