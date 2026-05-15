<?php

use App\Models\LootCard;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('renders the public Bonanza Loot Deck reference page with seeded cards', function () {
    // Seed the catalog as the seeder does — confirm the page surfaces them.
    $this->seed(\Database\Seeders\LootCardSeeder::class);

    $response = $this->get(route('tools.bonanza_loot_deck'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tools/BonanzaLootDeck')
        ->has('cards', 54),
    );
});

it('seeds 54 cards: 13 per suit + 2 jokers, idempotent on re-run', function () {
    $this->seed(\Database\Seeders\LootCardSeeder::class);
    $this->seed(\Database\Seeders\LootCardSeeder::class); // idempotency check

    expect(LootCard::count())->toBe(54);
    expect(LootCard::where('suit', 'crow')->count())->toBe(13);
    expect(LootCard::where('suit', 'mask')->count())->toBe(13);
    expect(LootCard::where('suit', 'ram')->count())->toBe(13);
    expect(LootCard::where('suit', 'tome')->count())->toBe(13);
    expect(LootCard::where('suit', 'joker')->count())->toBe(2);
});

it('lets a super_admin update a Loot Card effect text', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $admin = User::factory()->create()->assignRole('super_admin');

    $this->seed(\Database\Seeders\LootCardSeeder::class);
    $card = LootCard::where('suit', 'crow')->first();

    $this->actingAs($admin)
        ->post(route('admin.loot_cards.update', $card->slug), [
            'name' => $card->name,
            'effect_a' => 'Draw a card.',
            'effect_b' => 'Discard a card.',
        ])
        ->assertRedirect(route('admin.loot_cards.index'));

    expect($card->fresh()->effect_a)->toBe('Draw a card.');
    expect($card->fresh()->effect_b)->toBe('Discard a card.');
});

it('blocks non-super_admins from the loot card admin', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $regular = User::factory()->create();

    $this->actingAs($regular)
        ->get(route('admin.loot_cards.index'))
        ->assertForbidden();
});

it('coerces string "true"/"false" signature flags from FormData submits', function () {
    // Regression: `(bool) "false"` is `true` in PHP — Inertia's forceFormData
    // serializes booleans as those literal strings.
    $this->seed(\Database\Seeders\LootCardSeeder::class);
    $card = \App\Models\LootCard::where('suit', 'crow')->first();
    $signature = \App\Models\Action::factory()->create();
    $plain = \App\Models\Action::factory()->create();

    $card->syncSideActions('a', [
        ['action_id' => $signature->id, 'is_signature_action' => 'true'],
        ['action_id' => $plain->id, 'is_signature_action' => 'false'],
    ]);

    $card->load('sideAActions');
    $sig = $card->sideAActions->firstWhere('id', $signature->id);
    $not = $card->sideAActions->firstWhere('id', $plain->id);
    expect((bool) $sig->pivot->is_signature_action)->toBeTrue();
    expect((bool) $not->pivot->is_signature_action)->toBeFalse();

    // Re-sync with the flags flipped — confirms toggle-off works too,
    // which was the user-visible symptom.
    $card->syncSideActions('a', [
        ['action_id' => $signature->id, 'is_signature_action' => 'false'],
        ['action_id' => $plain->id, 'is_signature_action' => 'true'],
    ]);

    $card->load('sideAActions');
    $sig = $card->sideAActions->firstWhere('id', $signature->id);
    $not = $card->sideAActions->firstWhere('id', $plain->id);
    expect((bool) $sig->pivot->is_signature_action)->toBeFalse();
    expect((bool) $not->pivot->is_signature_action)->toBeTrue();
});

it('syncs side-A and side-B relations independently with the signature pivot flag', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $admin = User::factory()->create()->assignRole('super_admin');

    $this->seed(\Database\Seeders\LootCardSeeder::class);
    $card = \App\Models\LootCard::where('suit', 'crow')->first();

    $action = \App\Models\Action::factory()->create();
    $signatureAction = \App\Models\Action::factory()->create();
    $ability = \App\Models\Ability::factory()->create();
    $trigger = \App\Models\Trigger::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.loot_cards.update', $card->slug), [
            'name' => $card->name,
            'title_a' => 'Bag of Gold',
            'effect_a' => 'Side A blurb',
            'side_a_actions' => [
                ['action_id' => $action->id, 'is_signature_action' => false],
                ['action_id' => $signatureAction->id, 'is_signature_action' => true],
            ],
            'side_a_abilities' => [$ability->id],
            'side_b_triggers' => [$trigger->id],
        ])
        ->assertRedirect(route('admin.loot_cards.index'));

    $card->load(['sideAActions', 'sideAAbilities', 'sideBTriggers', 'sideBActions']);

    expect($card->sideAActions)->toHaveCount(2);
    expect($card->sideAAbilities->pluck('id')->all())->toBe([$ability->id]);
    expect($card->sideBTriggers->pluck('id')->all())->toBe([$trigger->id]);
    expect($card->sideBActions)->toHaveCount(0);

    // Signature flag rides correctly on the pivot.
    $sigRow = $card->sideAActions->firstWhere('id', $signatureAction->id);
    expect((bool) $sigRow->pivot->is_signature_action)->toBeTrue();
    $plainRow = $card->sideAActions->firstWhere('id', $action->id);
    expect((bool) $plainRow->pivot->is_signature_action)->toBeFalse();

    // Re-submit with a different roster — sync replaces, doesn't accumulate.
    $this->actingAs($admin)
        ->post(route('admin.loot_cards.update', $card->slug), [
            'name' => $card->name,
            'side_a_actions' => [['action_id' => $action->id, 'is_signature_action' => false]],
        ])
        ->assertRedirect();

    expect($card->fresh()->sideAActions()->count())->toBe(1);
    expect($card->fresh()->sideAAbilities()->count())->toBe(0);
});
