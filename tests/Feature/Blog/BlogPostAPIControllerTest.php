<?php

use App\Models\BlogCategory;
use App\Models\BlogPost;

it('index excludes news posts', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $articleCategory = BlogCategory::factory()->create();

    BlogPost::factory()->published()->create(['blog_category_id' => $newsCategory->id]);
    $articlePost = BlogPost::factory()->published()->create(['blog_category_id' => $articleCategory->id]);

    $response = $this->getJson('/api/blog/posts');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($articlePost->id)
        ->and($ids)->toHaveCount(1);
});
