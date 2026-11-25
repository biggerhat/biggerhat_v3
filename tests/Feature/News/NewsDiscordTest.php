<?php

use App\Enums\PermissionEnum;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

const NEWS_WEBHOOK = 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyzABCDEFGH-_12345';

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::ManageNews->value, 'guard_name' => 'web']);
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(PermissionEnum::ManageNews->value);
    $this->category = BlogCategory::factory()->news()->create();
    $this->post = BlogPost::factory()->published()->create(['blog_category_id' => $this->category->id, 'title' => 'Collection tracking is here', 'excerpt' => 'Mark each model as built or painted.']);
    config(['services.discord_news.webhook' => NEWS_WEBHOOK]);
});

it('posts a published news post to the Discord channel, with the link and no mentions, and remembers when', function () {
    Http::fake(['discord.com/*' => Http::response('', 204)]);

    $this->actingAs($this->editor)->post(route('admin.news.posts.discord', $this->post->slug), ['message' => 'Out now!'])->assertRedirect();

    Http::assertSent(function ($request) {
        $embed = $request['embeds'][0];

        return $request->url() === NEWS_WEBHOOK
            && $embed['title'] === 'Collection tracking is here'
            && $embed['description'] === 'Mark each model as built or painted.'
            && $embed['url'] === route('news.view', $this->post)
            && $request['content'] === 'Out now!'
            && $request['allowed_mentions'] === ['parse' => []];
    });
    expect($this->post->fresh()->discord_posted_at)->not->toBeNull();
});

it('uses the start of the post when it has no excerpt', function () {
    Http::fake(['discord.com/*' => Http::response('', 204)]);
    $this->post->update(['excerpt' => null, 'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'The first words of the post.']]]]]]);

    $this->actingAs($this->editor)->post(route('admin.news.posts.discord', $this->post->slug))->assertRedirect();

    Http::assertSent(fn ($request) => $request['embeds'][0]['description'] === 'The first words of the post.');
});

it('does not post a draft, and records nothing', function () {
    Http::fake();
    $draft = BlogPost::factory()->create(['blog_category_id' => $this->category->id, 'status' => 'draft', 'published_at' => null]);

    $this->actingAs($this->editor)->post(route('admin.news.posts.discord', $draft->slug))->assertRedirect();

    Http::assertNothingSent();
    expect($draft->fresh()->discord_posted_at)->toBeNull();
});

it('says so when Discord is not set up, and when Discord refuses', function () {
    Http::fake(['discord.com/*' => Http::response('nope', 500)]);

    $this->actingAs($this->editor)->post(route('admin.news.posts.discord', $this->post->slug))->assertRedirect();
    expect($this->post->fresh()->discord_posted_at)->toBeNull();

    config(['services.discord_news.webhook' => null]);
    Http::fake();
    $this->actingAs($this->editor)->post(route('admin.news.posts.discord', $this->post->slug))->assertRedirect();
    Http::assertNothingSent();
});

it('only sends to a real Discord webhook address', function () {
    Http::fake();
    config(['services.discord_news.webhook' => 'https://evil.example/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz']);

    $this->actingAs($this->editor)->post(route('admin.news.posts.discord', $this->post->slug))->assertRedirect();

    Http::assertNothingSent();
});

it('is for news posts and for people with manage_news only', function () {
    Http::fake();
    $article = BlogPost::factory()->published()->create(['blog_category_id' => BlogCategory::factory()->create()->id]);
    $other = User::factory()->create();

    $this->actingAs($this->editor)->post(route('admin.news.posts.discord', $article->slug))->assertNotFound();
    $this->actingAs($other)->post(route('admin.news.posts.discord', $this->post->slug))->assertForbidden();
    Http::assertNothingSent();
});

it('sends a guest to sign in', function () {
    Http::fake();

    $this->post(route('admin.news.posts.discord', $this->post->slug))->assertRedirect();
    Http::assertNothingSent();
});

it('rejects an over-long message', function () {
    Http::fake();

    $this->actingAs($this->editor)->postJson(route('admin.news.posts.discord', $this->post->slug), ['message' => str_repeat('a', 301)])->assertStatus(422);
    Http::assertNothingSent();
});

it('tells the news list whether Discord is ready and when each post was sent', function () {
    $this->post->forceFill(['discord_posted_at' => now()])->save();

    $this->actingAs($this->editor)->get(route('admin.news.posts.index'))->assertInertia(fn ($page) => $page
        ->where('discord_ready', true)
        ->where('posts.0.discord_posted_at', fn ($v) => $v !== null));
});
