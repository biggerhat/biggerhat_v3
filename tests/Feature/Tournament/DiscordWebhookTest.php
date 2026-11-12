<?php

use App\Enums\TournamentRoundStatusEnum;
use App\Jobs\PostTournamentToDiscord;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tournament = Tournament::factory()->create(['creator_id' => $this->owner->id, 'status' => 'draft', 'name' => 'Discord Open']);
    $this->hook = 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz-ABCDEF_0123456789';
    $this->connect = fn () => $this->tournament->update(['discord_webhook_url' => $this->hook]);
});

it('stores the webhook encrypted and never sends it to the browser', function () {
    $this->actingAs($this->owner)->putJson(route('tournaments.discord', $this->tournament), ['webhook_url' => $this->hook])->assertOk()->assertJsonPath('connected', true);

    expect(DB::table('tournaments')->where('id', $this->tournament->id)->value('discord_webhook_url'))->not->toContain('discord.com')
        ->and($this->tournament->fresh()->discord_webhook_url)->toBe($this->hook);

    $page = $this->actingAs($this->owner)->get(route('tournaments.manage', $this->tournament));
    $page->assertInertia(fn (Assert $p) => $p->where('discord_connected', true));
    expect($page->getContent())->not->toContain('abcdefghijklmnopqrstuvwxyz');
    $public = $this->get(route('tournaments.view', $this->tournament));
    expect($public->getContent())->not->toContain('abcdefghijklmnopqrstuvwxyz');
});

it('clears the connection with an empty value', function () {
    ($this->connect)();

    $this->actingAs($this->owner)->putJson(route('tournaments.discord', $this->tournament), ['webhook_url' => ''])->assertOk()->assertJsonPath('connected', false);

    expect($this->tournament->fresh()->discord_webhook_url)->toBeNull();
});

it('only accepts Discord webhook URLs', function (string $url) {
    $this->actingAs($this->owner)->putJson(route('tournaments.discord', $this->tournament), ['webhook_url' => $url])->assertJsonValidationErrors('webhook_url');
})->with([
    'http' => 'http://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz',
    'other host' => 'https://evil.test/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz',
    'internal address' => 'https://127.0.0.1/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz',
    'host trick' => 'https://discord.com.evil.test/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz',
    'extra path' => 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz/../../x',
    'no token' => 'https://discord.com/api/webhooks/123456789012345678/',
    'text' => 'not a url',
]);

it('only lets organizers connect and test', function () {
    $stranger = User::factory()->create();

    $this->putJson(route('tournaments.discord', $this->tournament), ['webhook_url' => $this->hook])->assertUnauthorized();
    $this->actingAs($stranger)->putJson(route('tournaments.discord', $this->tournament), ['webhook_url' => $this->hook])->assertForbidden();
    $this->actingAs($stranger)->postJson(route('tournaments.discord.test', $this->tournament))->assertForbidden();
});

it('sends a test message and reports whether Discord took it', function () {
    ($this->connect)();
    Http::fake([$this->hook => Http::sequence()->push('', 204)->push(['message' => 'Unknown Webhook'], 404)]);

    $this->actingAs($this->owner)->postJson(route('tournaments.discord.test', $this->tournament))->assertOk();
    $this->actingAs($this->owner)->postJson(route('tournaments.discord.test', $this->tournament))->assertStatus(422);

    Http::assertSent(fn ($request) => $request->url() === $this->hook
        && $request['embeds'][0]['title'] === 'Discord Open — Discord is connected'
        && $request['allowed_mentions'] === ['parse' => []]);
});

it('refuses to test without a webhook', function () {
    Http::fake();

    $this->actingAs($this->owner)->postJson(route('tournaments.discord.test', $this->tournament))->assertStatus(422);

    Http::assertNothingSent();
});

it('queues a post when registration opens, a round starts, an announcement goes out and the event finishes', function () {
    Queue::fake();
    ($this->connect)();
    $this->actingAs($this->owner);

    $this->putJson(route('tournaments.status', $this->tournament), ['status' => 'registration'])->assertOk();
    Queue::assertPushed(PostTournamentToDiscord::class, fn ($job) => $job->kind === 'registration');

    $this->putJson(route('tournaments.announcement', $this->tournament), ['announcement' => 'Lunch until 1:15'])->assertOk();
    Queue::assertPushed(PostTournamentToDiscord::class, fn ($job) => $job->kind === 'announcement' && $job->text === 'Lunch until 1:15');

    $this->putJson(route('tournaments.announcement', $this->tournament), ['announcement' => ''])->assertOk();
    Queue::assertPushedTimes(PostTournamentToDiscord::class, 2);
});

it('queues nothing for an event with no webhook', function () {
    Queue::fake();

    $this->actingAs($this->owner)->putJson(route('tournaments.status', $this->tournament), ['status' => 'registration'])->assertOk();
    $this->actingAs($this->owner)->putJson(route('tournaments.announcement', $this->tournament), ['announcement' => 'Hello'])->assertOk();

    Queue::assertNothingPushed();
});

it('posts the pairings when a round starts', function () {
    Http::fake();
    $t = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'name' => 'Pairing Cup', 'discord_webhook_url' => $this->hook]);
    $a = TournamentPlayer::factory()->for($t)->create(['display_name' => 'Alice']);
    $b = TournamentPlayer::factory()->for($t)->create(['display_name' => 'Bob']);
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 1, 'status' => TournamentRoundStatusEnum::Setup]);
    TournamentGame::factory()->for($round, 'round')->create(['player_one_id' => $a->id, 'player_two_id' => $b->id, 'table_number' => 3]);
    $round->update(['strategy_id' => App\Models\Strategy::factory()->create()->id, 'deployment' => App\Enums\DeploymentEnum::cases()[0], 'scheme_pool' => App\Models\Scheme::factory()->count(3)->create()->pluck('id')->all()]);

    $this->actingAs($this->owner)->putJson(route('tournaments.rounds.update', [$t, $round]), ['status' => 'in_progress'])->assertOk();

    Http::assertSent(fn ($request) => str_contains($request['embeds'][0]['title'], 'Round 1 has started')
        && str_contains($request['embeds'][0]['description'], '**Table 3** Alice vs Bob'));
});

it('posts the podium when the event finishes', function () {
    Http::fake();
    $t = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'name' => 'Podium Cup', 'discord_webhook_url' => $this->hook]);
    $a = TournamentPlayer::factory()->for($t)->create(['display_name' => 'Alice']);
    $b = TournamentPlayer::factory()->for($t)->create(['display_name' => 'Bob']);
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 1, 'status' => TournamentRoundStatusEnum::Completed]);
    TournamentGame::factory()->for($round, 'round')->create(['player_one_id' => $a->id, 'player_two_id' => $b->id, 'table_number' => 1, 'result' => 'completed', 'player_one_vp' => 9, 'player_two_vp' => 3]);

    $this->actingAs($this->owner)->putJson(route('tournaments.status', $t), ['status' => 'completed'])->assertOk();

    Http::assertSent(fn ($request) => str_contains($request['embeds'][0]['title'], 'Final results')
        && str_contains($request['embeds'][0]['description'], '🥇 Alice')
        && str_contains($request['embeds'][0]['fields'][0]['value'], 'results.pdf'));
});

it('does not post when a stored URL is not a Discord webhook, and never breaks the action when Discord is down', function () {
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('timeout'));
    $t = Tournament::factory()->create(['creator_id' => $this->owner->id, 'status' => 'draft']);
    $t->update(['discord_webhook_url' => 'https://evil.test/hook']);
    expect(app(App\Services\Discord\TournamentDiscord::class)->send($t->fresh(), ['title' => 'x']))->toBeFalse();
    Http::assertNothingSent();

    $t->update(['discord_webhook_url' => $this->hook]);
    $this->actingAs($this->owner)->putJson(route('tournaments.status', $t), ['status' => 'registration'])->assertOk();
});
