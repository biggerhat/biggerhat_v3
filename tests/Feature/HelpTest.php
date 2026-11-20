<?php

use App\Models\User;
use App\Support\Help\HelpTopics;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;

it('lists the help topics a visitor may open, without the pre-release ones', function () {
    $this->get(route('help.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Help/Index')->where('topics', ['crew-builder', 'game-tracker']));
});

it('adds the tabletop topic once the tabletop is open to the viewer', function () {
    Feature::for(null)->activate('experiment-tabletop');

    $this->get(route('help.index'))
        ->assertInertia(fn (Assert $page) => $page->where('topics', ['crew-builder', 'game-tracker', 'tabletop']));
});

it('shows a topic page for each public topic', function (string $slug) {
    $this->get(route('help.show', $slug))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Help/Show')->where('slug', $slug));
})->with(['crew-builder', 'game-tracker']);

it('hides the tabletop help (404) until the tabletop is open, as the tabletop is', function () {
    $this->get(route('help.show', 'tabletop'))->assertNotFound();

    Feature::for(null)->activate('experiment-tabletop');
    $this->get(route('help.show', 'tabletop'))->assertOk();
});

it('lets a beta tester open the tabletop help while the flag is off', function () {
    $tester = User::factory()->create();
    $tester->givePermissionTo(App\Enums\PermissionEnum::UseTabletopBeta->value);

    $this->actingAs($tester)->get(route('help.show', 'tabletop'))->assertOk();
});

it('answers 404 for a topic that does not exist', function () {
    $this->get(route('help.show', 'nothing-here'))->assertNotFound();
});

it('keeps the old tabletop help address working', function () {
    $this->get(route('tools.tabletop.help'))->assertRedirect(route('help.show', 'tabletop'));
});

it('has a text file for every topic it serves, whose "back to" route exists', function () {
    foreach (HelpTopics::SLUGS as $slug) {
        $file = resource_path('js/lib/help/topics/'.str($slug)->camel().'.ts');
        expect(file_exists($file))->toBeTrue("no help text for {$slug}: expected {$file}");

        preg_match("/slug: '([^']+)'/", file_get_contents($file), $slugMatch);
        expect($slugMatch[1] ?? null)->toBe($slug);
        preg_match("/route: '([^']+)'/", file_get_contents($file), $route);
        expect(Route::has($route[1] ?? ''))->toBeTrue("the {$slug} help links back to a route that does not exist: ".($route[1] ?? 'none'));
    }
});
