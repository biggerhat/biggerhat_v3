<?php

use App\Enums\PermissionEnum;
use App\Models\User;
use App\Support\CampaignAccess;
use Laravel\Pennant\Feature;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::UseCampaignMode->value]);
    Role::firstOrCreate(['name' => 'super_admin']);
});

it('rejects anonymous visitors when the feature flag is off', function () {
    expect(CampaignAccess::canUse(null))->toBeFalse();
});

it('rejects authed users with no permission when the feature flag is off', function () {
    $user = User::factory()->create();
    expect(CampaignAccess::canUse($user))->toBeFalse();
});

it('accepts users with the use_campaign_mode permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    expect(CampaignAccess::canUse($user))->toBeTrue();
});

it('accepts super_admin regardless of permission', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    expect(CampaignAccess::canUse($admin))->toBeTrue();
});

it('accepts everyone (including anonymous) when the flag is on', function () {
    Feature::for(null)->activate('m4e-campaign-mode');

    expect(CampaignAccess::canUse(null))->toBeTrue();
    expect(CampaignAccess::canUse(User::factory()->create()))->toBeTrue();
});
