<?php

use App\Models\Ability;
use App\Models\Action;
use App\Models\LootCard;
use App\Models\Trigger;
use Database\Seeders\LootCardEffectsSeeder;
use Database\Seeders\LootCardSeeder;

it('auto-links abilities mentioned by name in the effect text', function () {
    $this->seed(LootCardSeeder::class);

    Ability::factory()->create(['name' => 'Nefarious Pact']);
    Ability::factory()->create(['name' => 'Arcane Reservoir']);
    Ability::factory()->create(['name' => 'Unused Ability']);

    seedFiveOfCrows([
        'a' => [
            'title' => 'Pact with the Damned',
            'effect' => 'This model gains the following abilities: Nefarious Pact, Arcane Reservoir',
        ],
    ]);

    $card = LootCard::where('slug', 'five-of-crows')->first();
    expect($card->title_a)->toBe('Pact with the Damned');
    expect($card->effect_a)->toContain('Nefarious Pact');

    $sideAAbilityNames = $card->sideAAbilities->pluck('name')->all();
    expect($sideAAbilityNames)->toContain('Nefarious Pact');
    expect($sideAAbilityNames)->toContain('Arcane Reservoir');
    expect($sideAAbilityNames)->not->toContain('Unused Ability');
});

it('prefers longer matches over conflicting shorter substrings', function () {
    $this->seed(LootCardSeeder::class);

    Ability::factory()->create(['name' => 'Arcane']);
    Ability::factory()->create(['name' => 'Arcane Reservoir']);

    seedFiveOfCrows([
        'a' => [
            'effect' => 'This model gains Arcane Reservoir.',
        ],
    ]);

    $names = LootCard::where('slug', 'five-of-crows')->first()
        ->sideAAbilities->pluck('name')->all();

    expect($names)->toContain('Arcane Reservoir');
    expect($names)->not->toContain('Arcane');
});

it('attaches actions and flags signature_actions on the pivot', function () {
    $this->seed(LootCardSeeder::class);

    Action::factory()->create(['name' => 'Soul Sap']);
    Action::factory()->create(['name' => 'Distract']);

    seedFiveOfCrows([
        'b' => [
            'effect' => 'The model gains Soul Sap and Distract.',
            'signature_actions' => ['Soul Sap'],
        ],
    ]);

    $card = LootCard::where('slug', 'five-of-crows')->first();
    $actions = $card->sideBActions;

    expect($actions->pluck('name')->all())->toEqualCanonicalizing(['Soul Sap', 'Distract']);

    $soulSap = $actions->firstWhere('name', 'Soul Sap');
    $distract = $actions->firstWhere('name', 'Distract');
    expect((bool) $soulSap->pivot->is_signature_action)->toBeTrue();
    expect((bool) $distract->pivot->is_signature_action)->toBeFalse();
});

it('respects explicit attachment lists for entities not named in the prose', function () {
    $this->seed(LootCardSeeder::class);

    Ability::factory()->create(['name' => 'Hidden Ability']);
    Trigger::factory()->create(['name' => 'Hidden Trigger']);

    seedFiveOfCrows([
        'a' => [
            'effect' => 'Some opaque rules text that mentions nothing.',
            'abilities' => ['Hidden Ability'],
            'triggers' => ['Hidden Trigger'],
        ],
    ]);

    $card = LootCard::where('slug', 'five-of-crows')->first();
    expect($card->sideAAbilities->pluck('name')->all())->toContain('Hidden Ability');
    expect($card->sideATriggers->pluck('name')->all())->toContain('Hidden Trigger');
});

it('is idempotent — re-running detaches stale pivots first', function () {
    $this->seed(LootCardSeeder::class);

    Ability::factory()->create(['name' => 'Nefarious Pact']);
    Ability::factory()->create(['name' => 'Arcane Reservoir']);

    seedFiveOfCrows(['a' => ['effect' => 'Gains Nefarious Pact and Arcane Reservoir.']]);
    seedFiveOfCrows(['a' => ['effect' => 'Now only gains Nefarious Pact.']]);

    $names = LootCard::where('slug', 'five-of-crows')->first()
        ->sideAAbilities->pluck('name')->all();

    expect($names)->toContain('Nefarious Pact');
    expect($names)->not->toContain('Arcane Reservoir');
});

function seedFiveOfCrows(array $data): void
{
    $seeder = new class extends LootCardEffectsSeeder
    {
        public array $effectsOverride = [];

        protected function effects(): array
        {
            return $this->effectsOverride;
        }
    };
    $seeder->effectsOverride = ['five-of-crows' => $data];
    $seeder->run();
}
