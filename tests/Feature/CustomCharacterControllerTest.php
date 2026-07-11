<?php

use App\Models\CustomCharacter;
use App\Models\User;

function ccValidPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Character',
        'faction' => 'guild',
        'health' => 10,
        'base' => 30,
        'defense' => 5,
        'willpower' => 5,
        'speed' => 5,
        'actions' => [],
        'abilities' => [],
    ], $overrides);
}

it('requires auth for the index', function () {
    $this->get(route('tools.card_creator.index'))->assertRedirect(route('login'));
});

it('index only lists the current user\'s own characters', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    CustomCharacter::create(array_merge(ccValidPayload(['name' => 'Mine']), ['user_id' => $user->id]));
    CustomCharacter::create(array_merge(ccValidPayload(['name' => 'Not Mine']), ['user_id' => $other->id]));

    $this->actingAs($user)
        ->get(route('tools.card_creator.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('characters', 1)->where('characters.0.name', 'Mine'));
});

it('requires auth to store a character', function () {
    $this->postJson(route('tools.card_creator.store'), ccValidPayload())->assertUnauthorized();
});

it('stores a character and redirects to its edit page', function () {
    $user = User::factory()->create();

    $resp = $this->actingAs($user)->postJson(route('tools.card_creator.store'), ccValidPayload());

    $resp->assertOk()->assertJson(['success' => true]);
    $character = CustomCharacter::where('user_id', $user->id)->firstOrFail();
    expect($resp->json('redirect'))->toBe(route('tools.card_creator.edit', $character->id));
    expect($character->faction->value)->toBe('guild');
});

it('rejects store with an invalid faction — regression for the enum-cast crash', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tools.card_creator.store'), ccValidPayload(['faction' => 'not_a_real_faction']))
        ->assertJsonValidationErrors('faction');

    expect(CustomCharacter::where('user_id', $user->id)->exists())->toBeFalse();
});

it('rejects store with an invalid base size', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tools.card_creator.store'), ccValidPayload(['base' => 35]))
        ->assertJsonValidationErrors('base');
});

it('rejects store with an oversized action stone_cost — regression for the render-hang bug', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tools.card_creator.store'), ccValidPayload([
            'actions' => [['name' => 'Zap', 'type' => 'attack', 'stone_cost' => 999999]],
        ]))
        ->assertJsonValidationErrors('actions.0.stone_cost');
});

it('rejects store with an oversized nested trigger stone_cost', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tools.card_creator.store'), ccValidPayload([
            'actions' => [[
                'name' => 'Zap', 'type' => 'attack',
                'triggers' => [['name' => 'Bad Trigger', 'stone_cost' => 500]],
            ]],
        ]))
        ->assertJsonValidationErrors('actions.0.triggers.0.stone_cost');
});

it('rejects store with an invalid action type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tools.card_creator.store'), ccValidPayload([
            'actions' => [['name' => 'Zap', 'type' => 'not_a_real_type']],
        ]))
        ->assertJsonValidationErrors('actions.0.type');
});

it('rejects store missing required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tools.card_creator.store'), [])
        ->assertJsonValidationErrors(['name', 'faction', 'health', 'base', 'defense', 'willpower', 'speed']);
});

it('lets the owner view the edit page', function () {
    $user = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id]));

    $this->actingAs($user)
        ->get(route('tools.card_creator.edit', $character->id))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('character.id', $character->id));
});

it('blocks a non-owner from the edit page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $owner->id]));

    $this->actingAs($other)
        ->get(route('tools.card_creator.edit', $character->id))
        ->assertForbidden();
});

it('lets the owner update their character', function () {
    $user = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id]));

    $this->actingAs($user)
        ->putJson(route('tools.card_creator.update', $character->id), ccValidPayload(['name' => 'Renamed']))
        ->assertOk()->assertJson(['success' => true]);

    expect($character->fresh()->name)->toBe('Renamed');
});

it('blocks a non-owner from updating a character', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $owner->id]));

    $this->actingAs($other)
        ->putJson(route('tools.card_creator.update', $character->id), ccValidPayload(['name' => 'Hijacked']))
        ->assertForbidden();

    expect($character->fresh()->name)->not->toBe('Hijacked');
});

it('rejects an update with an invalid faction the same as store', function () {
    $user = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id]));

    $this->actingAs($user)
        ->putJson(route('tools.card_creator.update', $character->id), ccValidPayload(['faction' => 'nonsense']))
        ->assertJsonValidationErrors('faction');
});

it('update preserves campaign-leader invariants regardless of submitted values', function () {
    $user = User::factory()->create();
    $leader = CustomCharacter::create(array_merge(ccValidPayload(), [
        'user_id' => $user->id,
        'is_campaign_leader' => true,
        'station' => 'master',
        'generates_stone' => true,
        'is_unhirable' => false,
        'cost' => null,
    ]));

    $this->actingAs($user)
        ->putJson(route('tools.card_creator.update', $leader->id), ccValidPayload([
            'station' => 'minion',
            'generates_stone' => false,
            'is_unhirable' => true,
            'cost' => 8,
        ]))
        ->assertOk();

    $leader->refresh();
    expect($leader->station->value)->toBe('master');
    expect($leader->generates_stone)->toBeTrue();
    expect($leader->is_unhirable)->toBeFalse();
    expect($leader->cost)->toBeNull();
});

it('lets the owner delete their character', function () {
    $user = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id]));

    $this->actingAs($user)
        ->deleteJson(route('tools.card_creator.destroy', $character->id))
        ->assertOk()->assertJson(['success' => true]);

    expect(CustomCharacter::find($character->id))->toBeNull();
});

it('blocks a non-owner from deleting a character', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $owner->id]));

    $this->actingAs($other)
        ->deleteJson(route('tools.card_creator.destroy', $character->id))
        ->assertForbidden();

    expect(CustomCharacter::find($character->id))->not->toBeNull();
});

it('serves the public share page without auth, regardless of is_public', function () {
    $user = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id, 'is_public' => false]));

    $this->get(route('tools.card_creator.share', $character->share_code))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('character.id', $character->id)->where('creator_name', $user->name));
});

it('returns 404 for an unknown share code', function () {
    $this->get(route('tools.card_creator.share', 'does-not-exist'))->assertNotFound();
});

it('serves the headless-Chrome capture page without auth', function () {
    $user = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(['name' => 'Capture Me']), ['user_id' => $user->id]));

    $this->get(route('tools.card_creator.capture', $character->share_code))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('card.name', 'Capture Me')->where('card.faction', 'guild'));
});
