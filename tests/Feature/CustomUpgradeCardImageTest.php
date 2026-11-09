<?php

use App\Events\CardImageGenerated;
use App\Jobs\Campaign\GenerateCustomUpgradeCardImage;
use App\Jobs\Middleware\LimitConcurrentRenders;
use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use App\Models\User;
use App\Services\Campaign\CustomUpgradeCardImageGenerator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

function cuiUpgrade(User $user, array $overrides = []): CustomUpgrade
{
    return CustomUpgrade::create(array_merge(['user_id' => $user->id, 'name' => 'Test Upgrade', 'domain' => 'character', 'faction' => 'guild', 'content_blocks' => []], $overrides));
}

it('queues one delayed, coalesced render per burst of card edits, and none for unrelated fields', function () {
    Bus::fake();
    Cache::flush();
    $user = User::factory()->create();

    $upgrade = cuiUpgrade($user);
    Bus::assertDispatchedTimes(GenerateCustomUpgradeCardImage::class, 1);

    $upgrade->update(['name' => 'Renamed']);
    $upgrade->update(['content_blocks' => [['type' => 'text', 'text' => 'hi']]]);
    Bus::assertDispatchedTimes(GenerateCustomUpgradeCardImage::class, 1);

    // The job releasing its marker as it starts lets the next edit queue again.
    Cache::forget(CustomUpgrade::renderPendingKey($upgrade->id));
    $upgrade->update(['notes' => 'private']);
    Bus::assertDispatchedTimes(GenerateCustomUpgradeCardImage::class, 1);
    $upgrade->update(['faction' => 'arcanists']);
    Bus::assertDispatchedTimes(GenerateCustomUpgradeCardImage::class, 2);
});

it('renders the saved images, announces them to the owner, and clears the pending flags', function () {
    Event::fake([CardImageGenerated::class]);
    Storage::fake('public');
    $user = User::factory()->create();
    $upgrade = cuiUpgrade($user);
    Cache::put(CustomUpgrade::renderPendingKey($upgrade->id), true, 60);
    expect($upgrade->cardImagePending())->toBeTrue();

    $generator = new class extends CustomUpgradeCardImageGenerator
    {
        protected function capture(string $url, string $selector): string
        {
            return 'png';
        }

        protected function mergeSideBySide(string $frontPath, string $backPath, string $outPath): void {}
    };

    (new GenerateCustomUpgradeCardImage($upgrade->id))->handle($generator);

    $upgrade->refresh();
    expect($upgrade->front_image)->toBe("custom-upgrades/{$upgrade->id}/front.png")
        ->and($upgrade->card_image_generated_at)->not->toBeNull()
        ->and($upgrade->cardImagePending())->toBeFalse();
    Storage::disk('public')->assertExists("custom-upgrades/{$upgrade->id}/front.png");
    Event::assertDispatched(CardImageGenerated::class, fn ($e) => $e->kind === 'custom_upgrade' && $e->id === $upgrade->id && $e->userId === $user->id);
});

it('serves the capture page publicly with only what the card needs', function () {
    $user = User::factory()->create();
    $upgrade = cuiUpgrade($user, ['notes' => 'SECRET']);

    $this->get(route('tools.card_creator.upgrades.capture', $upgrade->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('CardCreator/UpgradeCapture')->has('upgrade.name')->missing('upgrade.notes')->missing('upgrade.user_id'))
        ->assertDontSee('SECRET');

    $this->get(route('tools.card_creator.upgrades.capture', 'nope'))->assertNotFound();
});

it('flags a pending render on the index and lets only the owner re-render or duplicate', function () {
    Bus::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $upgrade = cuiUpgrade($user);
    Cache::forget(CustomUpgrade::renderPendingKey($upgrade->id));
    Bus::fake();

    $this->actingAs($user)->get(route('tools.card_creator.index'))
        ->assertInertia(fn ($page) => $page->where('upgrades.0.card_image_pending', false));

    $this->actingAs($other)->postJson(route('tools.card_creator.upgrades.rerender', $upgrade->id))->assertForbidden();
    $this->actingAs($other)->postJson(route('tools.card_creator.upgrades.duplicate', $upgrade->id))->assertForbidden();

    $this->actingAs($user)->postJson(route('tools.card_creator.upgrades.rerender', $upgrade->id))->assertOk();
    Bus::assertDispatched(GenerateCustomUpgradeCardImage::class);

    $res = $this->actingAs($user)->postJson(route('tools.card_creator.upgrades.duplicate', $upgrade->id))->assertOk();
    $copy = CustomUpgrade::where('user_id', $user->id)->where('id', '!=', $upgrade->id)->firstOrFail();
    expect($copy->name)->toBe('Test Upgrade (Copy)')
        ->and($copy->share_code)->not->toBe($upgrade->share_code)
        ->and($res->json('redirect'))->toBe(route('tools.card_creator.upgrades.edit', $copy->id));
});

it('removes saved images when a card is deleted, and when the owning account is deleted', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $upgrade = cuiUpgrade($user);
    $character = CustomCharacter::create(['user_id' => $user->id, 'name' => 'C', 'faction' => 'guild', 'health' => 5, 'defense' => 5, 'willpower' => 5, 'speed' => 5, 'base' => 30]);
    $disk = Storage::disk('public');
    $disk->put("custom-upgrades/{$upgrade->id}/front.png", 'x');
    $disk->put("custom-characters/{$character->id}/front.png", 'x');

    $this->actingAs($user)->deleteJson(route('tools.card_creator.upgrades.destroy', $upgrade->id))->assertOk();
    $disk->assertMissing("custom-upgrades/{$upgrade->id}/front.png");

    $other = cuiUpgrade($user, ['name' => 'Other']);
    $disk->put("custom-upgrades/{$other->id}/front.png", 'x');
    $user->delete();
    $disk->assertMissing("custom-upgrades/{$other->id}/front.png");
    $disk->assertMissing("custom-characters/{$character->id}/front.png");
});

it('only lets a limited number of renders run at once, releasing the rest', function () {
    Cache::flush();
    config(['services.browsershot.max_concurrent_renders' => 1]);
    $middleware = new LimitConcurrentRenders;
    $ran = 0;

    $held = Cache::lock('card-render-slot:0', 60);
    expect($held->get())->toBeTrue();

    $job = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('release')->once()->with(5);
    $middleware->handle($job, function () use (&$ran) {
        $ran++;
    });
    expect($ran)->toBe(0);

    $held->release();
    $middleware->handle($job, function () use (&$ran) {
        $ran++;
    });
    expect($ran)->toBe(1)
        // The slot is freed again once the job is done.
        ->and(Cache::lock('card-render-slot:0', 5)->get())->toBeTrue();
});

function cuiPng(): string
{
    $img = imagecreatetruecolor(1100, 1900);
    ob_start();
    imagepng($img);
    imagedestroy($img);

    return ob_get_clean();
}

it('saves a small WebP thumbnail beside a rendered front, and tolerates an unreadable render', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $upgrade = cuiUpgrade($user);

    $generator = new class extends CustomUpgradeCardImageGenerator
    {
        protected function capture(string $url, string $selector): string
        {
            return cuiPng();
        }

        protected function mergeSideBySide(string $frontPath, string $backPath, string $outPath): void {}
    };
    $generator->generate($upgrade);

    $upgrade->refresh();
    expect($upgrade->thumb_image)->toBe("custom-upgrades/{$upgrade->id}/thumb.webp");
    [$w, $h] = getimagesize(Storage::disk('public')->path($upgrade->thumb_image));
    expect($w)->toBe(480)->and($h)->toBeBetween(829, 830);
    expect(Storage::disk('public')->size($upgrade->thumb_image))->toBeLessThan(Storage::disk('public')->size("custom-upgrades/{$upgrade->id}/front.png"));
});

it('backfills thumbnails for existing saved images without re-rendering', function () {
    Storage::fake('public');
    Bus::fake();
    $user = User::factory()->create();
    $upgrade = cuiUpgrade($user);
    Storage::disk('public')->put("custom-upgrades/{$upgrade->id}/front.png", cuiPng());
    $upgrade->forceFill(['front_image' => "custom-upgrades/{$upgrade->id}/front.png"])->saveQuietly();

    $this->artisan('app:backfill-card-thumbnails')->assertSuccessful();

    expect($upgrade->fresh()->thumb_image)->toBe("custom-upgrades/{$upgrade->id}/thumb.webp");
    Storage::disk('public')->assertExists("custom-upgrades/{$upgrade->id}/thumb.webp");
    Bus::assertDispatchedTimes(GenerateCustomUpgradeCardImage::class, 1); // only the creation one
});

it('holds the last render slot back for priority jobs', function () {
    Cache::flush();
    config(['services.browsershot.max_concurrent_renders' => 2]);
    $ran = 0;
    $run = function (LimitConcurrentRenders $m) use (&$ran) {
        $job = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $job->shouldReceive('release')->with(5)->zeroOrMoreTimes();
        $m->handle($job, function () use (&$ran) {
            $ran++;
        });
    };

    $held = Cache::lock('card-render-slot:0', 60);
    $held->get();

    $run(new LimitConcurrentRenders(priority: false)); // slot 0 busy, slot 1 reserved -> released, not run
    expect($ran)->toBe(0);
    $run(new LimitConcurrentRenders); // priority may use slot 1
    expect($ran)->toBe(1);
});
