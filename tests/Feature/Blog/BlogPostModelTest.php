<?php

use App\Models\BlogCategory;
use App\Models\BlogPost;

it('scopeNews returns only posts whose category is flagged is_news', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $articleCategory = BlogCategory::factory()->create();

    $newsPost = BlogPost::factory()->create(['blog_category_id' => $newsCategory->id]);
    $articlePost = BlogPost::factory()->create(['blog_category_id' => $articleCategory->id]);

    $result = BlogPost::news()->pluck('id');

    expect($result)->toContain($newsPost->id)
        ->and($result)->not->toContain($articlePost->id);
});

it('scopeExcludingNews includes uncategorized posts (blog_category_id null)', function () {
    $newsCategory = BlogCategory::factory()->news()->create();

    $newsPost = BlogPost::factory()->create(['blog_category_id' => $newsCategory->id]);
    $uncategorizedPost = BlogPost::factory()->create(['blog_category_id' => null]);

    $result = BlogPost::excludingNews()->pluck('id');

    expect($result)->toContain($uncategorizedPost->id)
        ->and($result)->not->toContain($newsPost->id);
});

it('scopeExcludingNews excludes news posts but includes regular article posts', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $articleCategory = BlogCategory::factory()->create();

    $newsPost = BlogPost::factory()->create(['blog_category_id' => $newsCategory->id]);
    $articlePost = BlogPost::factory()->create(['blog_category_id' => $articleCategory->id]);

    $result = BlogPost::excludingNews()->pluck('id');

    expect($result)->toContain($articlePost->id)
        ->and($result)->not->toContain($newsPost->id);
});

it('plainTextContent flattens the TipTap content tree into plain text', function () {
    $post = BlogPost::factory()->make([
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'world.']]],
            ],
        ],
    ]);

    expect($post->plainTextContent())->toBe('Hello world.');
});
