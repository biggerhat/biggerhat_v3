<?php

use App\Enums\PermissionEnum;
use App\Models\Campaign\BackAlleyDoctorResult;
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
});

it('store accepts removed_and_reflip as a valid outcome_kind', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.back-alley-doctor.store'), [
            'name' => 'How many fingers do you need?',
            'body' => 'Test body.',
            'flip_value_min' => 9,
            'flip_value_max' => 9,
            'is_black_joker' => false,
            'is_red_joker' => false,
            'outcome_kind' => 'removed_and_reflip',
        ])
        ->assertRedirect(route('admin.campaign.back-alley-doctor.index'));

    $row = BackAlleyDoctorResult::firstWhere('name', 'How many fingers do you need?');
    expect($row)->not->toBeNull();
    expect($row->outcome_kind->value)->toBe('removed_and_reflip');
});

it('store rejects an outcome_kind that is not a real BackAlleyDoctorOutcomeEnum value', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.back-alley-doctor.store'), [
            'name' => 'Bogus',
            'body' => 'x',
            'is_black_joker' => false,
            'is_red_joker' => false,
            'outcome_kind' => 'not_a_real_outcome',
        ])
        ->assertSessionHasErrors('outcome_kind');
});
