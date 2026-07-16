<?php

use App\Enums\FactionEnum;
use App\Enums\GameSystemEnum;
use App\Enums\PackageCategoryEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Package;

const FIXTURE = __DIR__.'/../../Fixtures/malifaux_box_contents_sample.json';

function seedBoxContentsFixtureData(): void
{
    Character::factory()->create(['name' => 'Sandeep Desai', 'title' => 'Font of Magic', 'display_name' => 'Sandeep Desai, Font of Magic', 'faction' => FactionEnum::Arcanists]);
    Character::factory()->create(['name' => 'Four Winds Golem', 'title' => null, 'display_name' => 'Four Winds Golem', 'faction' => FactionEnum::Arcanists]);
    $kandara = Character::factory()->create(['name' => 'Kandara', 'title' => null, 'display_name' => 'Kandara', 'faction' => FactionEnum::Arcanists]);
    $academic = Keyword::factory()->create(['name' => 'Academic']);
    $academic->characters()->attach($kandara);
    Character::factory()->create(['name' => 'Sandeep Desai', 'title' => 'The Quiet Flame', 'display_name' => 'Sandeep Desai, The Quiet Flame', 'faction' => FactionEnum::Arcanists]);
    Character::factory()->create(['name' => 'Sonnia Criid', 'title' => 'Unrelenting', 'display_name' => 'Sonnia Criid, Unrelenting', 'faction' => FactionEnum::Guild]);
    Character::factory()->create(['name' => 'Pandora', 'title' => 'Tyrant-Torn', 'display_name' => 'Pandora, Tyrant-Torn', 'faction' => FactionEnum::Neverborn]);
    Character::factory()->create(['name' => 'Bête Noire', 'title' => null, 'display_name' => 'Bête Noire', 'faction' => FactionEnum::Neverborn]);

    Package::factory()->create(['name' => 'Malifaux Fourth Edition: Sandeep Desai, Font of Magic', 'game_system' => GameSystemEnum::Malifaux]);
    Package::factory()->create(['name' => 'Sandeep The Quiet Flame', 'game_system' => GameSystemEnum::Malifaux]);
    Package::factory()->create(['name' => 'Sonnia Criid, Unrelenting', 'game_system' => GameSystemEnum::Malifaux]);
    Package::factory()->create(['name' => 'Malifaux Fourth Edition: Pandora, Tyrant-Torn', 'game_system' => GameSystemEnum::Malifaux]);
}

it('dry-runs without writing any data', function () {
    seedBoxContentsFixtureData();

    $this->artisan('app:sync-malifaux-box-contents --file='.FIXTURE)->assertSuccessful();

    $package = Package::where('name', 'Malifaux Fourth Edition: Sandeep Desai, Font of Magic')->first();
    expect($package->characters()->count())->toBe(0);
    expect($package->legacy_m3e_name)->toBeNull();
});

it('matches a box name shorthand to its real Package name and syncs quantities', function () {
    seedBoxContentsFixtureData();

    $this->artisan('app:sync-malifaux-box-contents --commit --file='.FIXTURE)->assertSuccessful();

    $package = Package::where('name', 'Malifaux Fourth Edition: Sandeep Desai, Font of Magic')->first();
    $characters = $package->characters()->get();

    expect($characters)->toHaveCount(3);
    expect($characters->firstWhere('display_name', 'Kandara')->pivot->quantity)->toBe(3);
    expect((bool) $characters->firstWhere('display_name', 'Kandara')->pivot->special_order)->toBeTrue();
    expect((bool) $characters->firstWhere('display_name', 'Four Winds Golem')->pivot->special_order)->toBeFalse();
    expect($package->fresh()->legacy_m3e_name)->toBe('Maintain the Balance');
});

it('tolerates a single-word typo in the box name via fuzzy token matching', function () {
    seedBoxContentsFixtureData();

    $this->artisan('app:sync-malifaux-box-contents --commit --file='.FIXTURE)->assertSuccessful();

    // Source PDF box name is "Sandeep The Quite Flame" (typo) but should
    // still resolve to the real "Sandeep The Quiet Flame" Package.
    $package = Package::where('name', 'Sandeep The Quiet Flame')->first();
    expect($package->characters()->count())->toBe(1);
    expect($package->characters()->first()->display_name)->toBe('Sandeep Desai, The Quiet Flame');
});

it('matches a bare character name against a titled display_name', function () {
    seedBoxContentsFixtureData();

    $this->artisan('app:sync-malifaux-box-contents --commit --file='.FIXTURE)->assertSuccessful();

    $package = Package::where('name', 'Sonnia Criid, Unrelenting')->first();
    expect($package->characters()->count())->toBe(1);
});

it('reports unmatched boxes and characters without erroring', function () {
    seedBoxContentsFixtureData();

    $this->artisan('app:sync-malifaux-box-contents --file='.FIXTURE)
        ->expectsOutputToContain('Some Unreleased Box Nobody Has')
        ->assertSuccessful();
});

it('matches a hyphenated display_name against the PDF\'s space-separated spelling', function () {
    seedBoxContentsFixtureData();

    $this->artisan('app:sync-malifaux-box-contents --commit --file='.FIXTURE)->assertSuccessful();

    // PDF lists "Pandora, Tyrant Torn" (space) but the real display_name is
    // "Pandora, Tyrant-Torn" (hyphen) — these must normalize to the same
    // thing rather than being treated as different words.
    $package = Package::where('name', 'Malifaux Fourth Edition: Pandora, Tyrant-Torn')->first();
    expect($package->characters()->pluck('display_name'))->toContain('Pandora, Tyrant-Torn');
});

it('matches a diacritic display_name against the PDF\'s plain-ASCII spelling', function () {
    seedBoxContentsFixtureData();

    $this->artisan('app:sync-malifaux-box-contents --commit --file='.FIXTURE)->assertSuccessful();

    // PDF lists "Bete Noire" (plain ASCII) but the real display_name is
    // "Bête Noire" (accented) — Str::ascii() should reconcile the two.
    $package = Package::where('name', 'Malifaux Fourth Edition: Pandora, Tyrant-Torn')->first();
    expect($package->characters()->pluck('display_name'))->toContain('Bête Noire');
});

it('creates a new auto-generated Package for a box with no Package match, deriving factions and keywords from its characters', function () {
    seedBoxContentsFixtureData();

    $this->artisan('app:sync-malifaux-box-contents --commit --file='.FIXTURE)->assertSuccessful();

    $package = Package::where('name', 'Totally New Unreleased Box')->first();

    expect($package)->not->toBeNull();
    expect($package->is_auto_generated)->toBeTrue();
    expect($package->category)->toBe(PackageCategoryEnum::Other);
    expect($package->factions)->toBe([FactionEnum::Arcanists->value]);
    expect($package->characters()->first()->pivot->quantity)->toBe(2);
    expect($package->keywords()->pluck('name'))->toContain('Academic');
});

it('does not create any Packages during a dry run', function () {
    seedBoxContentsFixtureData();

    $countBefore = Package::count();

    $this->artisan('app:sync-malifaux-box-contents --file='.FIXTURE)->assertSuccessful();

    expect(Package::count())->toBe($countBefore);
    expect(Package::where('name', 'Totally New Unreleased Box')->exists())->toBeFalse();
});

it('does not mark an already-matched Package as auto-generated', function () {
    seedBoxContentsFixtureData();

    $this->artisan('app:sync-malifaux-box-contents --commit --file='.FIXTURE)->assertSuccessful();

    $package = Package::where('name', 'Malifaux Fourth Edition: Sandeep Desai, Font of Magic')->first();
    expect($package->is_auto_generated)->toBeFalse();
});

it('does not overwrite an existing legacy_m3e_name on repeated runs', function () {
    seedBoxContentsFixtureData();

    Package::where('name', 'Malifaux Fourth Edition: Sandeep Desai, Font of Magic')
        ->first()
        ->update(['legacy_m3e_name' => 'Manually Curated Value']);

    $this->artisan('app:sync-malifaux-box-contents --commit --file='.FIXTURE)->assertSuccessful();

    $package = Package::where('name', 'Malifaux Fourth Edition: Sandeep Desai, Font of Magic')->first();
    expect($package->legacy_m3e_name)->toBe('Manually Curated Value');
});
