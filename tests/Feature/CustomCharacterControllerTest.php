<?php

use App\Enums\Campaign\CampaignStatusEnum;
use App\Events\CampaignCrewUpdated;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignTotemTemplate;
use App\Models\CustomCharacter;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

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

function ccCrewWithStatus(User $user, CampaignStatusEnum $status): CampaignCrew
{
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id, 'status' => $status->value]);

    return CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
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

it('preserves an action\'s source_character_id through a save in the generic editor', function () {
    // Regression: the generic editor previously had no validation rule for
    // actions.*.source_character_id, so validated() silently stripped it on
    // every save — which then made the Leader Builder's cost-cap re-validation
    // treat the action as a freeform/no-source entry on its next save.
    $user = User::factory()->create();
    $ally = \App\Models\Character::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload([
        'actions' => [[
            'name' => 'Borrowed Slash', 'type' => 'attack', 'source_id' => 1, 'source_character_id' => $ally->id,
        ]],
    ]), ['user_id' => $user->id]));

    $this->actingAs($user)
        ->putJson(route('tools.card_creator.update', $character->id), ccValidPayload([
            'actions' => [[
                'name' => 'Borrowed Slash', 'type' => 'attack', 'source_id' => 1, 'source_character_id' => $ally->id,
            ]],
        ]))
        ->assertOk();

    expect($character->fresh()->actions[0]['source_character_id'])->toBe($ally->id);
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

it('update lets a Campaign Leader\'s tag be changed, unlike the other locked campaign invariants', function () {
    $user = User::factory()->create();
    $leader = CustomCharacter::create(array_merge(ccValidPayload([
        'tag' => 'bruiser',
    ]), [
        'user_id' => $user->id,
        'is_campaign_leader' => true,
        'station' => 'master',
        'generates_stone' => true,
        'is_unhirable' => false,
        'cost' => null,
    ]));

    $this->actingAs($user)
        ->putJson(route('tools.card_creator.update', $leader->id), ccValidPayload(['tag' => 'strategist']))
        ->assertOk();

    expect($leader->fresh()->tag)->toBe('strategist');
});

it('update broadcasts CampaignCrewUpdated when a campaign leader is edited', function () {
    Event::fake([CampaignCrewUpdated::class]);

    $user = User::factory()->create();
    $crew = ccCrewWithStatus($user, CampaignStatusEnum::Active);
    $leader = CustomCharacter::create(array_merge(ccValidPayload(), [
        'user_id' => $user->id,
        'is_campaign_leader' => true,
        'campaign_crew_id' => $crew->id,
        'station' => 'master',
        'generates_stone' => true,
        'is_unhirable' => false,
        'cost' => null,
    ]));

    $this->actingAs($user)
        ->putJson(route('tools.card_creator.update', $leader->id), ccValidPayload(['name' => 'Renamed Leader']))
        ->assertOk();

    Event::assertDispatched(CampaignCrewUpdated::class, fn ($e) => $e->crew->id === $crew->id);
});

it('update broadcasts CampaignCrewUpdated when a campaign totem is edited', function () {
    Event::fake([CampaignCrewUpdated::class]);

    $user = User::factory()->create();
    $crew = ccCrewWithStatus($user, CampaignStatusEnum::Active);
    $totem = CustomCharacter::create(array_merge(ccValidPayload(), [
        'user_id' => $user->id,
        'is_campaign_totem' => true,
        'campaign_crew_id' => $crew->id,
        'station' => null,
        'is_unhirable' => true,
        'cost' => null,
    ]));

    $this->actingAs($user)
        ->putJson(route('tools.card_creator.update', $totem->id), ccValidPayload(['name' => 'Renamed Totem']))
        ->assertOk();

    Event::assertDispatched(CampaignCrewUpdated::class, fn ($e) => $e->crew->id === $crew->id);
});

it('update does not broadcast for a non-campaign character', function () {
    Event::fake([CampaignCrewUpdated::class]);

    $user = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id]));

    $this->actingAs($user)
        ->putJson(route('tools.card_creator.update', $character->id), ccValidPayload(['name' => 'Renamed']))
        ->assertOk();

    Event::assertNotDispatched(CampaignCrewUpdated::class);
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

it('blocks deleting a Campaign Leader — it\'s still referenced by the crew\'s advancement log and game history', function () {
    $user = User::factory()->create();
    $crew = ccCrewWithStatus($user, CampaignStatusEnum::Active);
    $leader = CustomCharacter::create(array_merge(ccValidPayload(), [
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
    ]));

    $this->actingAs($user)
        ->deleteJson(route('tools.card_creator.destroy', $leader->id))
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    expect(CustomCharacter::find($leader->id))->not->toBeNull();
});

it('blocks deleting a Campaign Totem for the same reason', function () {
    $user = User::factory()->create();
    $crew = ccCrewWithStatus($user, CampaignStatusEnum::Active);
    $totem = CustomCharacter::create(array_merge(ccValidPayload(), [
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_totem' => true,
    ]));

    $this->actingAs($user)
        ->deleteJson(route('tools.card_creator.destroy', $totem->id))
        ->assertStatus(422);

    expect(CustomCharacter::find($totem->id))->not->toBeNull();
});

it('blocks deleting a superseded (non-current) Campaign Leader row too — it\'s still historical game data', function () {
    $user = User::factory()->create();
    $crew = ccCrewWithStatus($user, CampaignStatusEnum::Active);
    $oldLeader = CustomCharacter::create(array_merge(ccValidPayload(), [
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => false,
        'replaced_at' => now(),
    ]));

    $this->actingAs($user)
        ->deleteJson(route('tools.card_creator.destroy', $oldLeader->id))
        ->assertStatus(422);

    expect(CustomCharacter::find($oldLeader->id))->not->toBeNull();
});

it('allows deleting a Campaign Leader once the owning campaign has ended', function () {
    Bus::fake();
    $user = User::factory()->create();
    $crew = ccCrewWithStatus($user, CampaignStatusEnum::Ended);
    $leader = CustomCharacter::create(array_merge(ccValidPayload(), [
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
    ]));

    $this->actingAs($user)
        ->deleteJson(route('tools.card_creator.destroy', $leader->id))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(CustomCharacter::find($leader->id))->toBeNull();
});

it('allows deleting a Campaign Totem once the owning campaign has been deleted', function () {
    // Regression: is_campaign_totem never gets cleared when the owning
    // Campaign is deleted — the deletion guard must treat "no matching live
    // Campaign for this crew" as "no longer live" rather than blocking forever
    // purely because the flag is still set.
    Bus::fake();
    $user = User::factory()->create();
    $crew = ccCrewWithStatus($user, CampaignStatusEnum::Active);
    $totem = CustomCharacter::create(array_merge(ccValidPayload(), [
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_totem' => true,
    ]));

    $crew->campaign->delete();

    $this->actingAs($user)
        ->deleteJson(route('tools.card_creator.destroy', $totem->id))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(CustomCharacter::find($totem->id))->toBeNull();
});

it('index flags campaign_still_live=true only while the linked campaign is genuinely active', function () {
    // Regression: the frontend's delete button used to disable purely on
    // is_campaign_leader/is_campaign_totem, which never clear on their own —
    // so a Leader from an ended (or deleted) campaign could never be deleted
    // from this list even though destroy() itself already allowed it.
    $user = User::factory()->create();

    $activeCrew = ccCrewWithStatus($user, CampaignStatusEnum::Active);
    $liveLeader = CustomCharacter::create(array_merge(ccValidPayload(['name' => 'Live Leader']), [
        'user_id' => $user->id,
        'campaign_crew_id' => $activeCrew->id,
        'is_campaign_leader' => true,
    ]));

    $endedCrew = ccCrewWithStatus($user, CampaignStatusEnum::Ended);
    $endedLeader = CustomCharacter::create(array_merge(ccValidPayload(['name' => 'Ended Leader']), [
        'user_id' => $user->id,
        'campaign_crew_id' => $endedCrew->id,
        'is_campaign_leader' => true,
    ]));

    $deletedCrew = ccCrewWithStatus($user, CampaignStatusEnum::Active);
    $deletedCrew->campaign->delete();
    $deletedCampaignTotem = CustomCharacter::create(array_merge(ccValidPayload(['name' => 'Deleted Campaign Totem']), [
        'user_id' => $user->id,
        'campaign_crew_id' => $deletedCrew->id,
        'is_campaign_totem' => true,
    ]));

    $unlinked = CustomCharacter::create(array_merge(ccValidPayload(['name' => 'Never Linked']), ['user_id' => $user->id]));

    $response = $this->actingAs($user)->get(route('tools.card_creator.index'))->assertOk();

    $byName = collect($response->viewData('page')['props']['characters'])->keyBy('name');
    expect($byName['Live Leader']['campaign_still_live'])->toBeTrue();
    expect($byName['Ended Leader']['campaign_still_live'])->toBeFalse();
    expect($byName['Deleted Campaign Totem']['campaign_still_live'])->toBeFalse();
    expect($byName['Never Linked']['campaign_still_live'])->toBeFalse();
});

it('never lists a Totem Template among a user\'s own custom characters — it lives on a separate table now', function () {
    // Regression: Totem Templates used to be CustomCharacter rows
    // (is_campaign_totem_template=true) owned via user_id by whichever admin
    // created them — undistinguished from that admin's own personal cards in
    // this exact index. Now they live on campaign_totem_templates, a table
    // this query never touches, so a template can never leak in here.
    $admin = User::factory()->create();
    CampaignTotemTemplate::factory()->create(['name' => 'Spirit Familiar 7']);
    CustomCharacter::create(array_merge(ccValidPayload(['name' => 'My Real Card']), ['user_id' => $admin->id]));

    $response = $this->actingAs($admin)->get(route('tools.card_creator.index'))->assertOk();

    $names = collect($response->viewData('page')['props']['characters'])->pluck('name')->all();
    expect($names)->toEqual(['My Real Card']);
});

it('cannot resolve a Totem Template id through the generic destroy route at all', function () {
    // Regression: the old destroy() guard only checked is_campaign_leader/
    // is_campaign_totem, never is_campaign_totem_template, so a template
    // could be deleted through this route with zero protection. Now the
    // route model-binds against CustomCharacter specifically — a Totem
    // Template's id lives in a disjoint table/id-space, so this 404s
    // structurally rather than relying on an app-level guard to catch it.
    $admin = User::factory()->create();
    $template = CampaignTotemTemplate::factory()->create();

    expect(CustomCharacter::find($template->id))->toBeNull();

    $this->actingAs($admin)
        ->deleteJson(route('tools.card_creator.destroy', $template->id))
        ->assertNotFound();
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

it('capture appends Unique and the Bruiser/Strategist tag as display characteristics for a Campaign Leader (pg 18)', function () {
    $user = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload([
        'name' => 'Upstart Leader',
        'is_campaign_leader' => true,
        'tag' => 'bruiser',
        'characteristics' => ['Living'],
    ]), ['user_id' => $user->id]));

    $this->get(route('tools.card_creator.capture', $character->share_code))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('card.characteristics', ['Living', 'Unique', 'Bruiser']));

    // The persisted column itself is untouched — this is a display-only
    // addition so it doesn't round-trip into the Leader Builder edit form
    // and get duplicated on the next save.
    expect($character->fresh()->characteristics)->toBe(['Living']);
});

it('capture does not add a tag characteristic for a non-leader custom character', function () {
    $user = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload([
        'name' => 'Regular Card',
        'characteristics' => ['Living'],
    ]), ['user_id' => $user->id]));

    $this->get(route('tools.card_creator.capture', $character->share_code))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('card.characteristics', ['Living']));
});

// ─── Library art (CardArt) ───

it('offers only active library art in the editor, plus the art already chosen', function () {
    $user = User::factory()->create();
    $active = \App\Models\CardArt::factory()->create(['name' => 'Active']);
    $retired = \App\Models\CardArt::factory()->inactive()->create(['name' => 'Retired']);
    $retiredButChosen = \App\Models\CardArt::factory()->inactive()->create(['name' => 'Chosen']);
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id, 'card_art_id' => $retiredButChosen->id]));

    $this->actingAs($user)->get(route('tools.card_creator.create'))
        ->assertInertia(fn ($page) => $page->has('card_arts', 1)->where('card_arts.0.id', $active->id));

    $this->actingAs($user)->get(route('tools.card_creator.edit', $character->id))
        ->assertInertia(fn ($page) => $page->has('card_arts', 2)->where('card_arts.0.name', 'Active')->where('card_arts.1.name', 'Chosen'));
    expect($retired->is_active)->toBeFalse();
});

it('stores a chosen art id and rejects unknown or retired art', function () {
    $user = User::factory()->create();
    $art = \App\Models\CardArt::factory()->create();
    $retired = \App\Models\CardArt::factory()->inactive()->create();

    $this->actingAs($user)->postJson(route('tools.card_creator.store'), ccValidPayload(['card_art_id' => $art->id]))->assertOk();
    expect(CustomCharacter::where('user_id', $user->id)->first()->card_art_id)->toBe($art->id);

    $this->actingAs($user)->postJson(route('tools.card_creator.store'), ccValidPayload(['card_art_id' => 99999]))->assertUnprocessable()->assertJsonValidationErrors('card_art_id');
    $this->actingAs($user)->postJson(route('tools.card_creator.store'), ccValidPayload(['card_art_id' => $retired->id]))->assertUnprocessable()->assertJsonValidationErrors('card_art_id');
});

it('lets a character keep retired art on update and clear it with null', function () {
    $user = User::factory()->create();
    $retired = \App\Models\CardArt::factory()->inactive()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id, 'card_art_id' => $retired->id]));

    $this->actingAs($user)->putJson(route('tools.card_creator.update', $character->id), ccValidPayload(['name' => 'Renamed', 'card_art_id' => $retired->id]))->assertOk();
    expect($character->fresh()->card_art_id)->toBe($retired->id);

    $this->actingAs($user)->putJson(route('tools.card_creator.update', $character->id), ccValidPayload(['card_art_id' => null]))->assertOk();
    expect($character->fresh()->card_art_id)->toBeNull();
});

it('exposes the chosen art path on the share page and the capture page', function () {
    $user = User::factory()->create();
    $art = \App\Models\CardArt::factory()->create(['image_path' => 'card_arts/demo.webp']);
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id, 'card_art_id' => $art->id]));

    $this->get(route('tools.card_creator.share', $character->share_code))->assertInertia(fn ($page) => $page->where('card_art_path', 'card_arts/demo.webp'));
    $this->get(route('tools.card_creator.capture', $character->share_code))->assertInertia(fn ($page) => $page->where('card.characterImage', 'card_arts/demo.webp'));
});

it('stores art framing, rejects out-of-range values, and resets it when the art is removed', function () {
    $user = User::factory()->create();
    $art = \App\Models\CardArt::factory()->create();

    $this->actingAs($user)->postJson(route('tools.card_creator.store'), ccValidPayload(['card_art_id' => $art->id, 'card_art_x' => 20, 'card_art_y' => 80, 'card_art_zoom' => 175, 'card_art_dx' => -40, 'card_art_dy' => 100]))->assertOk();
    $character = CustomCharacter::where('user_id', $user->id)->firstOrFail();
    expect([$character->card_art_x, $character->card_art_y, $character->card_art_zoom, $character->card_art_dx, $character->card_art_dy])->toBe([20, 80, 175, -40, 100]);

    foreach ([['card_art_x' => 101], ['card_art_y' => -1], ['card_art_zoom' => 49], ['card_art_zoom' => 301], ['card_art_dx' => 101], ['card_art_dy' => -101]] as $bad) {
        $this->actingAs($user)->putJson(route('tools.card_creator.update', $character->id), ccValidPayload($bad))->assertUnprocessable();
    }

    $this->actingAs($user)->putJson(route('tools.card_creator.update', $character->id), ccValidPayload(['card_art_id' => null, 'card_art_x' => 20, 'card_art_zoom' => 175, 'card_art_dx' => 30]))->assertOk();
    expect([$character->fresh()->card_art_x, $character->fresh()->card_art_y, $character->fresh()->card_art_zoom, $character->fresh()->card_art_dx, $character->fresh()->card_art_dy])->toBe([50, 50, 100, 0, 0]);
});

it('defaults framing to centred and unzoomed, and a null framing value falls back to the default', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('tools.card_creator.store'), ccValidPayload(['card_art_x' => null, 'card_art_zoom' => null]))->assertOk();
    $character = CustomCharacter::where('user_id', $user->id)->firstOrFail();
    expect([$character->card_art_x, $character->card_art_y, $character->card_art_zoom])->toBe([50, 50, 100]);
});

it('exposes the framing to the headless capture page', function () {
    $user = User::factory()->create();
    $art = \App\Models\CardArt::factory()->create(['image_path' => 'card_arts/demo.webp']);
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id, 'card_art_id' => $art->id, 'card_art_x' => 10, 'card_art_y' => 90, 'card_art_zoom' => 200]));

    $this->get(route('tools.card_creator.capture', $character->share_code))
        ->assertInertia(fn ($page) => $page->where('card.characterImageFocus', ['x' => 10, 'y' => 90, 'zoom' => 200, 'dx' => 0, 'dy' => 0]));
});

it('lists each card with its saved image fields and the art fallback for the tile thumbnail', function () {
    $user = User::factory()->create();
    $art = \App\Models\CardArt::factory()->create(['image_path' => 'card_arts/tile.webp']);
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id, 'card_art_id' => $art->id]));
    $character->forceFill(['front_image' => 'custom-characters/1/front.png', 'combination_image' => 'custom-characters/1/combination.png', 'card_image_generated_at' => now()])->saveQuietly();

    $this->actingAs($user)->get(route('tools.card_creator.index'))
        ->assertInertia(fn ($page) => $page
            ->where('characters.0.card_art_path', 'card_arts/tile.webp')
            ->where('characters.0.front_image', 'custom-characters/1/front.png')
            ->where('characters.0.combination_image', 'custom-characters/1/combination.png')
            ->missing('characters.0.card_art'));
});

it('exposes the saved combination image on the public share page', function () {
    $user = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id]));
    $character->forceFill(['combination_image' => 'custom-characters/9/combination.png'])->saveQuietly();

    $this->get(route('tools.card_creator.share', $character->share_code))
        ->assertInertia(fn ($page) => $page->where('character.combination_image', 'custom-characters/9/combination.png'));
});

it('gives the crew builder a custom character\'s saved card images', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id]));
    $character->forceFill(['front_image' => 'custom-characters/5/front.png', 'back_image' => 'custom-characters/5/back.png'])->saveQuietly();

    $this->actingAs($user)->get(route('tools.crew_builder.editor'))
        ->assertInertia(fn ($page) => $page
            ->where('customCharacters.0.front_image', 'custom-characters/5/front.png')
            ->where('customCharacters.0.back_image', 'custom-characters/5/back.png'));
});

it('removes a deleted card\'s saved images from disk', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    $user = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id]));
    \Illuminate\Support\Facades\Storage::disk('public')->put("custom-characters/{$character->id}/front.png", 'x');

    $this->actingAs($user)->deleteJson(route('tools.card_creator.destroy', $character->id))->assertOk();

    \Illuminate\Support\Facades\Storage::disk('public')->assertMissing("custom-characters/{$character->id}/front.png");
});

it('flags a card whose image is waiting to render and lets the owner re-render it', function () {
    Bus::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id]));
    \Illuminate\Support\Facades\Cache::forget(CustomCharacter::renderPendingKey($character->id));
    Bus::fake();

    $this->actingAs($user)->get(route('tools.card_creator.index'))
        ->assertInertia(fn ($page) => $page->where('characters.0.card_image_pending', false));

    $this->actingAs($other)->postJson(route('tools.card_creator.rerender', $character->id))->assertForbidden();
    Bus::assertNotDispatched(\App\Jobs\Campaign\GenerateLeaderCardImage::class);

    $this->actingAs($user)->postJson(route('tools.card_creator.rerender', $character->id))->assertOk();
    Bus::assertDispatched(\App\Jobs\Campaign\GenerateLeaderCardImage::class);

    // A second click while it's rendering doesn't queue another.
    Bus::fake();
    $this->actingAs($user)->postJson(route('tools.card_creator.rerender', $character->id))->assertOk();
    Bus::assertNotDispatched(\App\Jobs\Campaign\GenerateLeaderCardImage::class);
    $this->actingAs($user)->get(route('tools.card_creator.index'))
        ->assertInertia(fn ($page) => $page->where('characters.0.card_image_pending', true));
});

it('does not leak the owner\'s private fields on the public share pages', function () {
    $user = User::factory()->create();
    $character = CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id, 'notes' => 'SECRET NOTE']));
    $upgrade = \App\Models\CustomUpgrade::create([
        'user_id' => $user->id, 'name' => 'Secret Upgrade', 'domain' => 'character', 'faction' => 'guild', 'notes' => 'SECRET UPGRADE NOTE',
    ]);

    $this->get(route('tools.card_creator.share', $character->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('character.display_name')->missing('character.notes')->missing('character.user_id')->missing('character.share_code'))
        ->assertDontSee('SECRET NOTE');

    $this->get(route('tools.card_creator.upgrades.share', $upgrade->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('upgrade.display_name')->missing('upgrade.notes')->missing('upgrade.user_id'))
        ->assertDontSee('SECRET UPGRADE NOTE');
});

it('caps how many custom characters a user can keep, and bounds name length', function () {
    $user = User::factory()->create();
    Bus::fake();

    $this->actingAs($user)->postJson(route('tools.card_creator.store'), ccValidPayload(['name' => str_repeat('a', 61)]))->assertUnprocessable()->assertJsonValidationErrors('name');

    foreach (range(1, \App\Http\Requests\CustomCharacterRequest::MAX_PER_USER) as $i) {
        CustomCharacter::create(array_merge(ccValidPayload(), ['user_id' => $user->id, 'name' => "Card {$i}"]));
    }

    $this->actingAs($user)->postJson(route('tools.card_creator.store'), ccValidPayload(['name' => 'One too many']))
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    // Editing an existing card is unaffected.
    $existing = CustomCharacter::where('user_id', $user->id)->first();
    $this->actingAs($user)->putJson(route('tools.card_creator.update', $existing->id), ccValidPayload(['name' => 'Renamed']))->assertOk();
});

it('duplicates a card into a plain copy with no campaign state, and only for its owner', function () {
    Bus::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $art = \App\Models\CardArt::factory()->create();
    $original = CustomCharacter::create(array_merge(ccValidPayload(), [
        'user_id' => $user->id, 'name' => 'Original', 'notes' => 'my notes', 'card_art_id' => $art->id, 'card_art_zoom' => 150, 'card_art_dx' => -20,
        'is_campaign_leader' => true, 'campaign_crew_id' => ccCrewWithStatus($user, CampaignStatusEnum::Active)->id,
    ]));

    $this->actingAs($other)->postJson(route('tools.card_creator.duplicate', $original->id))->assertForbidden();

    $res = $this->actingAs($user)->postJson(route('tools.card_creator.duplicate', $original->id))->assertOk();
    $copy = CustomCharacter::where('user_id', $user->id)->where('id', '!=', $original->id)->firstOrFail();

    expect($res->json('redirect'))->toBe(route('tools.card_creator.edit', $copy->id))
        ->and($copy->name)->toBe('Original (Copy)')
        ->and($copy->share_code)->not->toBe($original->share_code)
        ->and($copy->notes)->toBe('my notes')
        ->and([$copy->card_art_id, $copy->card_art_zoom, $copy->card_art_dx])->toBe([$art->id, 150, -20])
        ->and($copy->is_campaign_leader)->toBeFalse()
        ->and($copy->campaign_crew_id)->toBeNull();
});
