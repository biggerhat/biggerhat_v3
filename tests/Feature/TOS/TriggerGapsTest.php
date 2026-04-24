<?php

use App\Enums\TOS\TriggerTimingEnum;
use App\Models\TOS\Trigger;

it('margin_cost column stores the numeric Trigger cost', function () {
    $trigger = Trigger::factory()->marginCost(5)->create();

    $fresh = $trigger->fresh();
    expect($fresh->margin_cost)->toBe(5)
        ->and($fresh->suits)->toBeNull();
});

it('timing defaults to Default and can be set to Immediately', function () {
    $t1 = Trigger::factory()->create();
    $t2 = Trigger::factory()->immediately()->create();

    expect($t1->fresh()->timing)->toBe(TriggerTimingEnum::Default)
        ->and($t2->fresh()->timing)->toBe(TriggerTimingEnum::Immediately);
});
