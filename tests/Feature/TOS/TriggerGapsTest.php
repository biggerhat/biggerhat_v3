<?php

use App\Enums\TOS\TriggerTimingEnum;
use App\Models\TOS\Action;
use App\Models\TOS\Trigger;

it('margin_cost column stores the numeric Trigger cost', function () {
    $trigger = Trigger::factory()
        ->for(Action::factory()->create(), 'action')
        ->marginCost(5)
        ->create();

    $fresh = $trigger->fresh();
    expect($fresh->margin_cost)->toBe(5)
        ->and($fresh->suits)->toBeNull();
});

it('timing defaults to Default and can be set to Immediately', function () {
    $t1 = Trigger::factory()->for(Action::factory()->create(), 'action')->create();
    $t2 = Trigger::factory()->for(Action::factory()->create(), 'action')->immediately()->create();

    expect($t1->fresh()->timing)->toBe(TriggerTimingEnum::Default)
        ->and($t2->fresh()->timing)->toBe(TriggerTimingEnum::Immediately);
});
