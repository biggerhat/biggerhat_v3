<?php

use App\Enums\PermissionEnum;
use App\Models\Campaign\AdvancementAbility;
use App\Models\Campaign\AdvancementAction;
use App\Models\Campaign\AdvancementAttackMod;
use App\Models\Campaign\AdvancementTacticalMod;
use App\Models\Campaign\BackAlleyDoctorResult;
use App\Models\Campaign\CrewCardEffect;
use App\Models\Campaign\Equipment;
use App\Models\Campaign\Injury;
use App\Models\Campaign\LuckyMiss;
use App\Models\Campaign\SummoningAdvancement;
use App\Models\Campaign\Totem;
use App\Models\Campaign\WeeklyEvent;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

function campaignAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo([
        PermissionEnum::ViewCampaignCatalog->value,
        PermissionEnum::EditCampaignCatalog->value,
        PermissionEnum::DeleteCampaignCatalog->value,
        PermissionEnum::UseCampaignMode->value,
    ]);

    return $user;
}

/**
 * Each entry: [route prefix, model factory class, Inertia component, factory.create-time tweaks].
 * The factory class is invoked via `::factory()` to materialize a row for delete/edit tests.
 */
dataset('catalogs', [
    'crew-card-effects' => [
        'admin.campaign.crew-card-effects',
        CrewCardEffect::class,
        'Admin/Campaign/CrewCardEffect/Index',
    ],
    'equipment' => [
        'admin.campaign.equipment',
        Equipment::class,
        'Admin/Campaign/Equipment/Index',
    ],
    'injuries' => [
        'admin.campaign.injuries',
        Injury::class,
        'Admin/Campaign/Injury/Index',
    ],
    'lucky-miss' => [
        'admin.campaign.lucky-miss',
        LuckyMiss::class,
        'Admin/Campaign/LuckyMiss/Index',
    ],
    'back-alley-doctor' => [
        'admin.campaign.back-alley-doctor',
        BackAlleyDoctorResult::class,
        'Admin/Campaign/BackAlleyDoctor/Index',
    ],
    'advancement-attack-mod' => [
        'admin.campaign.advancement-attack-mod',
        AdvancementAttackMod::class,
        'Admin/Campaign/Advancement/Index',
    ],
    'advancement-tactical-mod' => [
        'admin.campaign.advancement-tactical-mod',
        AdvancementTacticalMod::class,
        'Admin/Campaign/Advancement/Index',
    ],
    'advancement-action' => [
        'admin.campaign.advancement-action',
        AdvancementAction::class,
        'Admin/Campaign/Advancement/Index',
    ],
    'advancement-ability' => [
        'admin.campaign.advancement-ability',
        AdvancementAbility::class,
        'Admin/Campaign/Advancement/Index',
    ],
    'totems' => [
        'admin.campaign.totems',
        Totem::class,
        'Admin/Campaign/Totem/Index',
    ],
    'summoning-advancements' => [
        'admin.campaign.summoning-advancements',
        SummoningAdvancement::class,
        'Admin/Campaign/SummoningAdvancement/Index',
    ],
    'weekly-events' => [
        'admin.campaign.weekly-events',
        WeeklyEvent::class,
        'Admin/Campaign/WeeklyEvent/Index',
    ],
]);

it('renders the index page', function (string $routePrefix, string $modelClass, string $component) {
    $modelClass::factory()->create();

    $this->actingAs(campaignAdmin())
        ->get(route("$routePrefix.index"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component)->has('items', 1));
})->with('catalogs');

it('redirects index for users without view permission via campaign.access (404 hide)', function (string $routePrefix, string $modelClass) {
    $user = User::factory()->create(['email_verified_at' => now()]);
    // Has admin-area entry via another perm so admin.any passes, but no
    // campaign perms → campaign.access fails → 404.
    $user->givePermissionTo(PermissionEnum::ViewKeyword->value);

    $this->actingAs($user)
        ->get(route("$routePrefix.index"))
        ->assertNotFound();
})->with('catalogs');

it('deletes a row with full perms', function (string $routePrefix, string $modelClass) {
    $row = $modelClass::factory()->create();

    $this->actingAs(campaignAdmin())
        ->post(route("$routePrefix.delete", $row->id))
        ->assertRedirect();

    expect($modelClass::find($row->id))->toBeNull();
})->with('catalogs');

it('blocks delete without delete permission', function (string $routePrefix, string $modelClass) {
    $row = $modelClass::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo([
        PermissionEnum::ViewCampaignCatalog->value,
        PermissionEnum::UseCampaignMode->value,
    ]);

    $this->actingAs($user)
        ->post(route("$routePrefix.delete", $row->id))
        ->assertForbidden();

    expect($modelClass::find($row->id))->not->toBeNull();
})->with('catalogs');
