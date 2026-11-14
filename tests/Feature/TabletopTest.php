<?php

use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;

it('hides the tabletop until its feature flag is on', function () {
    $this->get(route('tools.tabletop'))->assertNotFound();
});

it('renders the tabletop page when the flag is on', function () {
    Feature::for(null)->activate('experiment-tabletop');

    $this->get(route('tools.tabletop'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tools/Tabletop'));
});
