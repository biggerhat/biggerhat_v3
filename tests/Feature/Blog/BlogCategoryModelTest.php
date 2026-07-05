<?php

use App\Models\BlogCategory;

it('casts is_news to boolean', function () {
    $category = BlogCategory::factory()->news()->create();

    expect($category->fresh()->is_news)->toBeTrue();
});

it('scopeNews returns only is_news categories', function () {
    $news = BlogCategory::factory()->news()->create();
    $article = BlogCategory::factory()->create();

    $result = BlogCategory::news()->pluck('id');

    expect($result)->toContain($news->id)
        ->and($result)->not->toContain($article->id);
});

it('scopeExcludingNews returns only non-news categories', function () {
    $news = BlogCategory::factory()->news()->create();
    $article = BlogCategory::factory()->create();

    $result = BlogCategory::excludingNews()->pluck('id');

    expect($result)->toContain($article->id)
        ->and($result)->not->toContain($news->id);
});
