<?php

use App\Enums\CoopRoleEnum;
use App\Models\Cooperative\CoopClass;
use App\Models\Cooperative\CoopEncounter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists published encounters and classes grouped by role, hiding unpublished ones', function () {
    CoopEncounter::factory()->create(['number' => 1, 'name' => 'Smash and Grab', 'slug' => 'smash-and-grab']);
    CoopEncounter::factory()->unpublished()->create(['number' => 2, 'name' => 'Secret Draft']);
    CoopClass::factory()->role(CoopRoleEnum::Firepower)->create(['name' => 'Gunslinger']);
    CoopClass::factory()->unpublished()->create(['name' => 'Hidden Class']);

    $props = $this->get(route('cooperative.index'))->assertOk()->viewData('page')['props'];

    expect(collect($props['encounters'])->pluck('name')->all())->toBe(['Smash and Grab'])
        ->and(array_keys($props['classes']))->toBe(['firepower'])
        ->and($props['classes']['firepower'][0]['name'])->toBe('Gunslinger');
});

it('shows an encounter with the suggested classes it names and its neighbours', function () {
    $gunslinger = CoopClass::factory()->role(CoopRoleEnum::Firepower)->create(['name' => 'Gunslinger', 'slug' => 'gunslinger']);
    CoopEncounter::factory()->create(['number' => 1, 'slug' => 'one', 'name' => 'One']);
    $two = CoopEncounter::factory()->create([
        'number' => 2, 'slug' => 'two', 'name' => 'Two',
        'phases' => [['title' => 'Opening', 'role_order' => [['role' => 'firepower', 'class_slug' => 'gunslinger']]]],
    ]);
    CoopEncounter::factory()->create(['number' => 3, 'slug' => 'three', 'name' => 'Three']);

    $props = $this->get(route('cooperative.encounter', $two->slug))->assertOk()->viewData('page')['props'];

    expect($props['encounter']['name'])->toBe('Two')
        ->and(array_keys($props['classes']))->toBe(['gunslinger'])
        ->and($props['neighbors']['previous']['slug'])->toBe('one')
        ->and($props['neighbors']['next']['slug'])->toBe('three')
        ->and($gunslinger->name)->toBe('Gunslinger');
});

it('404s an unpublished or unknown encounter', function () {
    $hidden = CoopEncounter::factory()->unpublished()->create();

    $this->get(route('cooperative.encounter', $hidden->slug))->assertNotFound();
    $this->get(route('cooperative.encounter', 'nope'))->assertNotFound();
});
