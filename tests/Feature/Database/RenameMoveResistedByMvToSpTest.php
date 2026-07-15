<?php

use App\Models\Action;
use App\Models\Campaign\AdvancementAction;
use App\Models\CustomCharacter;

/**
 * The migration itself already ran (via RefreshDatabase) before this test's
 * database exists, so it had nothing to backfill yet. Re-run its up() (and
 * down()) directly against freshly-inserted legacy-shaped data to verify the
 * actual backfill logic, the same way you'd test any data migration.
 */
function runResistedByMigration(string $direction = 'up'): void
{
    $migration = require database_path('migrations/2026_07_15_090000_rename_move_resisted_by_mv_to_sp.php');
    $migration->{$direction}();
}

it('renames Mv to Sp on the actions table', function () {
    $action = Action::factory()->create(['resisted_by' => 'Mv']);
    $untouched = Action::factory()->create(['resisted_by' => 'Df']);

    runResistedByMigration();

    expect($action->fresh()->resisted_by)->toBe('Sp');
    expect($untouched->fresh()->resisted_by)->toBe('Df');
});

it('renames Mv to Sp inside a bespoke AdvancementAction stat_block, leaving lookup rows alone', function () {
    $bespoke = AdvancementAction::factory()->create([
        'stat_block' => ['type' => 'tactical', 'range' => 8, 'range_type' => 'ft', 'stat' => 5, 'resisted_by' => 'Mv', 'target_number' => null, 'damage' => 2],
    ]);
    $realAction = Action::factory()->create(['resisted_by' => 'Mv']);
    $lookup = AdvancementAction::factory()->lookup($realAction->id)->create();

    runResistedByMigration();

    expect($bespoke->fresh()->stat_block['resisted_by'])->toBe('Sp');
    // Lookup rows have no stat_block of their own — nothing to touch, the
    // linked Action row (already asserted in the sibling test) is what matters.
    expect($lookup->fresh()->stat_block)->toBeNull();
});

it('renames Mv to Sp inside every matching entry of custom_characters.actions', function () {
    $leader = CustomCharacter::create([
        'user_id' => \App\Models\User::factory()->create()->id,
        'name' => 'Migration Test Leader',
        'display_name' => 'Migration Test Leader',
        'slug' => 'migration-test-leader',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6, 'base' => 30,
        'actions' => [
            ['name' => 'Slash', 'resisted_by' => 'Mv'],
            ['name' => 'Cast', 'resisted_by' => 'Wp'],
        ],
    ]);

    runResistedByMigration();

    $fresh = $leader->fresh();
    expect($fresh->actions[0]['resisted_by'])->toBe('Sp');
    expect($fresh->actions[1]['resisted_by'])->toBe('Wp');
});

it('down() reverses all three back to Mv', function () {
    $action = Action::factory()->create(['resisted_by' => 'Sp']);
    $bespoke = AdvancementAction::factory()->create([
        'stat_block' => ['type' => 'tactical', 'range' => 8, 'range_type' => 'ft', 'stat' => 5, 'resisted_by' => 'Sp', 'target_number' => null, 'damage' => 2],
    ]);
    $leader = CustomCharacter::create([
        'user_id' => \App\Models\User::factory()->create()->id,
        'name' => 'Migration Rollback Leader',
        'display_name' => 'Migration Rollback Leader',
        'slug' => 'migration-rollback-leader',
        'faction' => \App\Enums\FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6, 'base' => 30,
        'actions' => [['name' => 'Slash', 'resisted_by' => 'Sp']],
    ]);

    runResistedByMigration('down');

    expect($action->fresh()->resisted_by)->toBe('Mv');
    expect($bespoke->fresh()->stat_block['resisted_by'])->toBe('Mv');
    expect($leader->fresh()->actions[0]['resisted_by'])->toBe('Mv');
});
