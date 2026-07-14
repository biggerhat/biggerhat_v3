<?php

use App\Enums\PermissionEnum;
use App\Jobs\Campaign\GenerateCrewCardImage;
use App\Models\Campaign\CampaignCrewCard;
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

it('queues a card regeneration when a Crew Card is created', function () {
    Bus::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.store'), [
            'name' => 'Fresh Effect',
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertRedirect();

    $row = CampaignCrewCard::firstWhere('name', 'Fresh Effect');
    Bus::assertDispatched(GenerateCrewCardImage::class, fn ($job) => $job->crewCardId === $row->id);
});

it('queues a card regeneration when a Crew Card is updated', function () {
    $row = CampaignCrewCard::factory()->create();

    Bus::fake();
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.update', $row->id), [
            'name' => 'Renamed Effect',
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertRedirect();

    Bus::assertDispatched(GenerateCrewCardImage::class, fn ($job) => $job->crewCardId === $row->id);
});

it('capture page renders name, body, and linked actions/abilities for the queue worker to screenshot', function () {
    $row = CampaignCrewCard::factory()->create(['name' => 'Ice Reflection', 'description' => 'Body text here.']);
    $action = \App\Models\Action::factory()->create(['name' => 'Icy Grasp']);
    $ability = \App\Models\Ability::factory()->create(['name' => 'Frozen Heart']);
    $row->actions()->attach($action->id, ['is_signature_action' => true]);
    $row->abilities()->attach($ability->id);

    $this->get(route('tools.card_creator.capture_crew_card', $row->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CardCreator/CaptureCrewCard')
            ->where('card.name', 'Ice Reflection')
            ->where('card.body', 'Body text here.')
            ->where('card.actions.0.name', 'Icy Grasp')
            ->where('card.actions.0.is_signature', true)
            ->where('card.abilities.0.name', 'Frozen Heart')
        );
});
