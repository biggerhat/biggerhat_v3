<?php

use App\Enums\PermissionEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TokenRemovalTimingEnum;
use App\Models\Strategy;
use App\Models\Token;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('stores a token with auto-removal timing and the general flag', function () {
    $this->actingAs($this->admin)->post(route('admin.tokens.store'), [
        'name' => 'Fast',
        'removal_timing' => TokenRemovalTimingEnum::EndOfTurn->value,
        'is_general' => true,
    ])->assertRedirect(route('admin.tokens.index'));

    $token = Token::where('name', 'Fast')->first();
    expect($token->removal_timing)->toBe(TokenRemovalTimingEnum::EndOfTurn)
        ->and($token->is_general)->toBeTrue();
});

it('links tokens to a Strategy and re-syncs on update', function () {
    $explosive = Token::factory()->create(['name' => 'Explosive']);
    $other = Token::factory()->create(['name' => 'Other']);
    $season = PoolSeasonEnum::cases()[0]->value;

    $this->actingAs($this->admin)->post(route('admin.strategies.store'), [
        'name' => 'Plant Explosives',
        'season' => $season,
        'setup' => 'Setup', 'rules' => 'Rules', 'scoring' => 'Scoring', 'additional_scoring' => 'More',
        'token_ids' => [$explosive->id],
    ])->assertRedirect(route('admin.strategies.index'));

    $strategy = Strategy::where('name', 'Plant Explosives')->first();
    expect($strategy->tokens->pluck('id')->all())->toBe([$explosive->id]);

    $this->actingAs($this->admin)->post(route('admin.strategies.update', $strategy->slug), [
        'name' => 'Plant Explosives',
        'season' => $season,
        'setup' => 'Setup', 'rules' => 'Rules', 'scoring' => 'Scoring', 'additional_scoring' => 'More',
        'token_ids' => [$other->id],
    ])->assertRedirect();

    expect($strategy->fresh()->tokens->pluck('id')->all())->toBe([$other->id]);
});
