<?php

use App\Enums\TOS\GarrisonFormatEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Garrison;
use App\Models\TOS\GarrisonUnit;
use App\Models\TOS\Unit;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->allegiance = Allegiance::factory()->earth()->create();
});

it('redirects guests trying to view the Garrisons index', function () {
    $this->get(route('tos.garrisons.index'))->assertRedirect(route('login'));
});

it("lists only the current user's Garrisons", function () {
    $mine = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create(['name' => 'Mine']);
    Garrison::factory()->forAllegiance($this->allegiance)->create(['name' => 'Theirs']); // some other user

    $this->actingAs($this->user)->get(route('tos.garrisons.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Garrisons/Index')
            ->has('garrisons', 1)
            ->where('garrisons.0.id', $mine->id)
        );
});

it('shows the create form with the format options', function () {
    $this->actingAs($this->user)->get(route('tos.garrisons.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Garrisons/Create')
            ->has('formats', count(GarrisonFormatEnum::cases()))
            ->has('allegiances')
        );
});

it('stores a new Garrison and redirects to the View page', function () {
    $payload = [
        'name' => 'First Wave',
        'allegiance_id' => $this->allegiance->id,
        'format' => GarrisonFormatEnum::TwoCommanders->value,
        'notes' => 'opener for the spring league',
    ];

    $response = $this->actingAs($this->user)->post(route('tos.garrisons.store'), $payload);

    $garrison = Garrison::where('name', 'First Wave')->first();
    expect($garrison)->not->toBeNull();
    expect($garrison->user_id)->toBe($this->user->id);
    expect($garrison->format)->toBe(GarrisonFormatEnum::TwoCommanders);

    $response->assertRedirect(route('tos.garrisons.view', $garrison->slug));
});

it('rejects an invalid format on store', function () {
    $this->actingAs($this->user)
        ->post(route('tos.garrisons.store'), [
            'name' => 'Bogus',
            'allegiance_id' => $this->allegiance->id,
            'format' => 'two_thousand_commanders',
        ])
        ->assertSessionHasErrors('format');
});

it('renders the View payload with format meta and violations', function () {
    $g = Garrison::factory()
        ->forUser($this->user)
        ->forAllegiance($this->allegiance)
        ->format(GarrisonFormatEnum::OneCommander)
        ->create();

    $unit = Unit::factory()->create(['scrip' => 50, 'name' => 'Bigshot']);
    GarrisonUnit::create(['garrison_id' => $g->id, 'unit_id' => $unit->id, 'is_commander' => false]);

    $this->actingAs($this->user)->get(route('tos.garrisons.view', $g->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Garrisons/View')
            ->where('format.value', 'one_commander')
            ->where('format.scrip_budget', 40)
            ->where('scrip_spent', 50)
            ->has('violations', 1)
        );
});

it('blocks non-owners from viewing a Garrison', function () {
    $other = User::factory()->create();
    $g = Garrison::factory()->forUser($other)->forAllegiance($this->allegiance)->create();

    $this->actingAs($this->user)->get(route('tos.garrisons.view', $g->slug))
        ->assertForbidden();
});

it('lets the owner toggle public', function () {
    $g = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create(['is_public' => false]);

    $this->actingAs($this->user)->post(route('tos.garrisons.toggle_public', $g->slug))->assertRedirect();

    expect($g->fresh()->is_public)->toBeTrue();
});

it('blocks non-owners from toggling public', function () {
    $other = User::factory()->create();
    $g = Garrison::factory()->forUser($other)->forAllegiance($this->allegiance)->create();

    $this->actingAs($this->user)->post(route('tos.garrisons.toggle_public', $g->slug))->assertForbidden();
});

it('lets the owner update name + format', function () {
    $g = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)
        ->format(GarrisonFormatEnum::OneCommander)->create(['name' => 'Old name']);

    $this->actingAs($this->user)->post(route('tos.garrisons.update', $g->slug), [
        'name' => 'New name',
        'format' => GarrisonFormatEnum::TwoCommanders->value,
    ])->assertRedirect(route('tos.garrisons.view', $g->slug));

    $fresh = $g->fresh();
    expect($fresh->name)->toBe('New name');
    expect($fresh->format)->toBe(GarrisonFormatEnum::TwoCommanders);
});

it('lets the owner delete a Garrison', function () {
    $g = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();

    $this->actingAs($this->user)->post(route('tos.garrisons.delete', $g->slug))
        ->assertRedirect(route('tos.garrisons.index'));

    expect(Garrison::find($g->id))->toBeNull();
});

it('blocks non-owners from deleting a Garrison', function () {
    $other = User::factory()->create();
    $g = Garrison::factory()->forUser($other)->forAllegiance($this->allegiance)->create();

    $this->actingAs($this->user)->post(route('tos.garrisons.delete', $g->slug))->assertForbidden();
    expect(Garrison::find($g->id))->not->toBeNull();
});

it('serves a public Garrison via share_code without auth', function () {
    $g = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create(['is_public' => true]);

    $this->get(route('tos.garrisons.shared', $g->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Garrisons/Shared')
            ->where('garrison.id', $g->id)
            ->where('garrison.user.id', $this->user->id)
        );
});

it('returns 404 for a private Garrison via share_code', function () {
    $g = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create(['is_public' => false]);

    $this->get(route('tos.garrisons.shared', $g->share_code))->assertNotFound();
});

it('streams a PDF for the owner', function () {
    $g = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();

    $response = $this->actingAs($this->user)->get(route('tos.garrisons.pdf', $g->slug));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('blocks non-owners from the PDF endpoint', function () {
    $other = User::factory()->create();
    $g = Garrison::factory()->forUser($other)->forAllegiance($this->allegiance)->create();

    $this->actingAs($this->user)->get(route('tos.garrisons.pdf', $g->slug))->assertForbidden();
});
