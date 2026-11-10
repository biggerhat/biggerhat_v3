<?php

use App\Models\TOS\Deployment;
use App\Models\TOS\Operation;
use Database\Seeders\TOS\DeploymentSeeder;
use Database\Seeders\TOS\OperationSeeder;

it('seeds the four Fields of Glory operations idempotently', function () {
    $this->seed(OperationSeeder::class);
    $this->seed(OperationSeeder::class);

    expect(Operation::count())->toBe(4);
    expect(Operation::pluck('slug')->all())->toContain('deep-recovery', 'build-fortifications', 'target-sighted', 'deliver-payload');
    expect(Operation::where('slug', 'deliver-payload')->value('victory_conditions'))->toContain('Commander unit is Killed');
});

it('seeds the four deployments keyed by suit', function () {
    $this->seed(DeploymentSeeder::class);
    $this->seed(DeploymentSeeder::class);

    expect(Deployment::count())->toBe(4);
    expect(Deployment::pluck('name', 'suit')->all())->toBe([
        'R' => 'Confrontation', 'C' => 'Corners', 'T' => 'Sideways', 'M' => 'Killing Field',
    ]);
});
