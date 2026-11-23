<?php

use App\Models\User;

it('shows the roadmap to guests and signed-in users', function () {
    $this->get(route('roadmap'))->assertOk()->assertInertia(fn ($page) => $page->component('Roadmap'));

    $this->actingAs(User::factory()->create())->get(route('roadmap'))->assertOk();
});

it('links to the roadmap from the footer and lists it in the sitemap', function () {
    expect(file_get_contents(resource_path('js/layouts/AppLayout.vue')))->toContain("route('roadmap')");

    expect($this->get('/sitemap.xml')->assertOk()->streamedContent())->toContain(route('roadmap'));
});
