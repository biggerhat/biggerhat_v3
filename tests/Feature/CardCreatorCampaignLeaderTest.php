<?php

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
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

it('card editor keeps a campaign totem station-less, cost-0, and hireable-for-free', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $totem = CustomCharacter::create([
        'user_id' => $user->id,
        'is_campaign_totem' => true,
        'current' => true,
        'share_code' => 'ttm-guard-1',
        'name' => 'Test Totem',
        'display_name' => 'Test Totem',
        'slug' => 'test-totem',
        'faction' => 'guild',
        'station' => null,
        'health' => 8, 'defense' => 4, 'willpower' => 4, 'speed' => 4,
        'base' => 30,
        'is_unhirable' => true,
        'cost' => null,
    ]);

    // The generic editor tries to give the totem a station and a cost and make
    // it hirable — the guard must keep it station-less, cost-0, and unhirable.
    $this->actingAs($user)
        ->putJson(route('tools.card_creator.update', $totem->id), [
            'name' => 'Test Totem',
            'faction' => 'guild',
            'station' => 'minion',
            'health' => 8,
            'base' => '30',
            'defense' => 4,
            'willpower' => 4,
            'speed' => 4,
            'cost' => 5,
            'is_unhirable' => false,
        ])
        ->assertOk();

    $totem->refresh();
    expect($totem->station)->toBeNull();
    expect($totem->cost)->toBeNull();
    expect($totem->is_unhirable)->toBeTrue();
    expect($totem->is_campaign_totem)->toBeTrue();
    expect($totem->current)->toBeTrue();
});

it('gives both a campaign leader and a campaign totem a back link to the Arsenal Sheet', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $leader = CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'share_code' => 'ldr-guard-2',
        'name' => 'Leader', 'display_name' => 'Leader', 'slug' => 'leader-2',
        'faction' => 'guild', 'station' => 'master',
        'health' => 12, 'defense' => 5, 'willpower' => 6, 'speed' => 5, 'base' => 30,
        'generates_stone' => true, 'is_unhirable' => false, 'cost' => null,
    ]);

    $totem = CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_totem' => true,
        'current' => true,
        'share_code' => 'ttm-guard-2',
        'name' => 'Totem', 'display_name' => 'Totem', 'slug' => 'totem-2',
        'faction' => 'guild', 'station' => null,
        'health' => 8, 'defense' => 4, 'willpower' => 4, 'speed' => 4, 'base' => 30,
        'is_unhirable' => true, 'cost' => null,
    ]);

    $expectedUrl = route('campaigns.crews.arsenal.show', [$crew->campaign_id, $crew->share_code]);

    $this->actingAs($user)
        ->get(route('tools.card_creator.edit', $leader->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('campaign_back_url', $expectedUrl));

    $this->actingAs($user)
        ->get(route('tools.card_creator.edit', $totem->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('campaign_back_url', $expectedUrl));
});
