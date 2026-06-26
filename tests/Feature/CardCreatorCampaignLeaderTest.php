<?php

use App\Models\CustomCharacter;
use App\Models\User;

it('card editor keeps a campaign leader as a cost-0 master and preserves campaign fields', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $leader = CustomCharacter::create([
        'user_id' => $user->id,
        'is_campaign_leader' => true,
        'current' => true,
        'archetype' => 'generalist',
        'tag' => 'bruiser',
        'share_code' => 'ldr-guard-1',
        'name' => 'Test Leader',
        'display_name' => 'Test Leader',
        'slug' => 'test-leader',
        'faction' => 'guild',
        'station' => 'master',
        'health' => 12, 'defense' => 5, 'willpower' => 6, 'speed' => 5,
        'base' => 30,
        'generates_stone' => true,
        'is_unhirable' => false,
        'cost' => null,
    ]);

    // The generic editor tries to demote the leader to a 7ss Minion — the guard
    // must keep it a cost-0, stone-generating Master and leave the campaign-only
    // fields (tag / is_campaign_leader / current) untouched.
    $this->actingAs($user)
        ->putJson(route('tools.card_creator.update', $leader->id), [
            'name' => 'Test Leader',
            'faction' => 'guild',
            'station' => 'minion',
            'health' => 12,
            'base' => '30',
            'defense' => 5,
            'willpower' => 6,
            'speed' => 5,
            'cost' => 7,
        ])
        ->assertOk();

    $leader->refresh();
    expect($leader->station->value)->toBe('master');
    expect($leader->cost)->toBeNull();
    expect($leader->generates_stone)->toBeTrue();
    expect($leader->is_unhirable)->toBeFalse();
    expect($leader->tag)->toBe('bruiser');
    expect($leader->is_campaign_leader)->toBeTrue();
    expect($leader->current)->toBeTrue();
});
