<?php

use App\Enums\CharacterStationEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Character;
use App\Models\CustomCharacter;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->stranger = User::factory()->create();
});

/**
 * CustomCharacter has no HasFactory (it's the shared Custom Card Creator
 * model, populated via the card builder rather than test factories) — build
 * a minimal, valid row directly, matching AftermathTest.php's buildLeaderFor().
 *
 * @param  array<string, mixed>  $overrides
 */
function customLeader(array $overrides = []): CustomCharacter
{
    $name = $overrides['name'] ?? fake()->unique()->words(2, true);

    return CustomCharacter::create(array_merge([
        'user_id' => User::factory()->create()->id,
        'name' => $name,
        'display_name' => $name,
        'slug' => \Illuminate\Support\Str::slug($name).'-'.fake()->unique()->numerify('###'),
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 14,
        'defense' => 5,
        'willpower' => 5,
        'speed' => 6,
        'base' => 30,
        'is_campaign_leader' => true,
        'current' => true,
    ], $overrides));
}

it('index denies users without view_campaign_catalog', function () {
    $this->stranger->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    $this->actingAs($this->stranger)
        ->get(route('admin.campaign.crew-cards.index'))
        ->assertForbidden();
});

it('index includes each row\'s linked master', function () {
    $master = Character::factory()->create(['station' => CharacterStationEnum::Master->value]);
    CampaignCrewCard::factory()->forOfficialMaster($master)->create(['name' => 'Ice Reflection']);
    CampaignCrewCard::factory()->create(['name' => 'Generic Effect']);

    $this->actingAs($this->admin)
        ->get(route('admin.campaign.crew-cards.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.0.master', null)
            ->where('items.1.master.display_name', $master->display_name)
            ->where('items.1.master_is_custom', false));
});

it('index flags a custom-Leader-linked crew card as custom', function () {
    $leader = customLeader();
    CampaignCrewCard::factory()->forCustomMaster($leader)->create(['name' => 'Homebrew Effect']);

    $this->actingAs($this->admin)
        ->get(route('admin.campaign.crew-cards.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.0.master.display_name', $leader->display_name)
            ->where('items.0.master_is_custom', true));
});

it('create page renders without a MissingAttributeException when masters/custom_masters exist', function () {
    // Regression: `masters`/`custom_masters` selected only ['id', 'display_name'],
    // but both Character and CustomCharacter append a computed `faction_color`
    // attribute on every serialization, and that accessor unconditionally
    // reads `faction` — under Model::shouldBeStrict(), a column-scoped select
    // without it throws the moment Inertia serializes the prop. This crashed
    // the Crew Card create/edit admin page in production with a 500.
    Character::factory()->create(['station' => CharacterStationEnum::Master->value]);
    customLeader();

    $this->actingAs($this->admin)
        ->get(route('admin.campaign.crew-cards.create'))
        ->assertOk();
});

it('edit page renders without a MissingAttributeException when masters/custom_masters exist', function () {
    Character::factory()->create(['station' => CharacterStationEnum::Master->value]);
    customLeader();
    $row = CampaignCrewCard::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.campaign.crew-cards.edit', $row->id))
        ->assertOk();
});

it('store creates a crew card linked to a master', function () {
    $master = Character::factory()->create(['station' => CharacterStationEnum::Master->value]);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.store'), [
            'name' => 'Ice Reflection',
            'description' => 'Body text.',
            'master_id' => $master->id,
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertRedirect(route('admin.campaign.crew-cards.index'));

    $row = CampaignCrewCard::firstWhere('name', 'Ice Reflection');
    expect($row)->not->toBeNull();
    expect($row->master_id)->toBe($master->id);
});

it('store creates a crew card linked to a custom-built Campaign Leader', function () {
    $leader = customLeader();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.store'), [
            'name' => 'Homebrew Effect',
            'description' => 'Body text.',
            'master_type' => 'custom',
            'master_id' => $leader->id,
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertRedirect(route('admin.campaign.crew-cards.index'));

    $row = CampaignCrewCard::firstWhere('name', 'Homebrew Effect');
    expect($row)->not->toBeNull();
    expect($row->master_id)->toBe($leader->id);
    expect($row->master_type)->toBe(CustomCharacter::class);
    expect($row->master)->toBeInstanceOf(CustomCharacter::class);
    expect($row->master->id)->toBe($leader->id);
});

it('store rejects a custom_character not flagged as a campaign leader as master_id', function () {
    $notALeader = customLeader(['is_campaign_leader' => false]);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.store'), [
            'name' => 'Bad Link',
            'master_type' => 'custom',
            'master_id' => $notALeader->id,
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertSessionHasErrors('master_id');
});

it('store rejects a non-Master character as master_id', function () {
    $minion = Character::factory()->create(['station' => CharacterStationEnum::Minion->value]);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.store'), [
            'name' => 'Bad Link',
            'master_id' => $minion->id,
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertSessionHasErrors('master_id');
});

it('store denies users without edit_campaign_catalog', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $viewer->givePermissionTo(PermissionEnum::ViewCampaignCatalog->value);

    $this->actingAs($viewer)
        ->post(route('admin.campaign.crew-cards.store'), [
            'name' => 'Nope',
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertForbidden();
});

it('update re-assigns master_id, including clearing it back to generic', function () {
    $masterA = Character::factory()->create(['station' => CharacterStationEnum::Master->value]);
    $masterB = Character::factory()->create(['station' => CharacterStationEnum::Master->value]);
    $row = CampaignCrewCard::factory()->create(['master_id' => $masterA->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.update', $row->id), [
            'name' => $row->name,
            'master_id' => $masterB->id,
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertRedirect();

    expect($row->fresh()->master_id)->toBe($masterB->id);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.update', $row->id), [
            'name' => $row->name,
            'master_id' => null,
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertRedirect();

    expect($row->fresh()->master_id)->toBeNull();
});

it('update re-assigns master_id from an official master to a custom Leader', function () {
    $officialMaster = Character::factory()->create(['station' => CharacterStationEnum::Master->value]);
    $customLeader = customLeader();
    $row = CampaignCrewCard::factory()->forOfficialMaster($officialMaster)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.update', $row->id), [
            'name' => $row->name,
            'master_type' => 'custom',
            'master_id' => $customLeader->id,
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertRedirect();

    $fresh = $row->fresh();
    expect($fresh->master_id)->toBe($customLeader->id);
    expect($fresh->master_type)->toBe(CustomCharacter::class);
    expect($fresh->master)->toBeInstanceOf(CustomCharacter::class);
});

it('store persists a Tier-4 borrow exclusion reason on an action and an ability', function () {
    $action = \App\Models\Action::factory()->create();
    $ability = \App\Models\Ability::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.store'), [
            'name' => 'Excluded Effects',
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
            'actions' => [
                ['id' => $action->id, 'is_signature' => false, 'borrow_exclusion' => 'power_bar'],
            ],
            'abilities' => [
                ['id' => $ability->id, 'borrow_exclusion' => 'card_swap'],
            ],
        ])
        ->assertRedirect();

    $row = CampaignCrewCard::firstWhere('name', 'Excluded Effects');
    expect($row->actions->first()->pivot->borrow_exclusion)->toBe('power_bar');
    expect($row->abilities->first()->pivot->borrow_exclusion)->toBe('card_swap');
});

it('store rejects an invalid borrow_exclusion value', function () {
    $action = \App\Models\Action::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.store'), [
            'name' => 'Bad Exclusion',
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
            'actions' => [
                ['id' => $action->id, 'is_signature' => false, 'borrow_exclusion' => 'not_a_real_reason'],
            ],
        ])
        ->assertSessionHasErrors('actions.0.borrow_exclusion');
});

it('update clears a borrow_exclusion back to eligible', function () {
    $action = \App\Models\Action::factory()->create();
    $row = CampaignCrewCard::factory()->create();
    $row->actions()->attach($action->id, ['borrow_exclusion' => 'power_bar']);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.update', $row->id), [
            'name' => $row->name,
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
            'actions' => [
                ['id' => $action->id, 'is_signature' => false, 'borrow_exclusion' => null],
            ],
        ])
        ->assertRedirect();

    expect($row->fresh()->actions->first()->pivot->borrow_exclusion)->toBeNull();
});

it('delete removes the crew card', function () {
    $row = CampaignCrewCard::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.delete', $row->id))
        ->assertRedirect();

    expect(CampaignCrewCard::find($row->id))->toBeNull();
});
