<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeNotification(User $user, array $attrs): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['message' => 'x'],
        ...$attrs,
    ]);
}

it('removes old read notifications and year-old unread ones, keeping recent and unread', function () {
    $user = User::factory()->create();
    $oldRead = makeNotification($user, ['read_at' => now()->subDays(40)]);
    $recentRead = makeNotification($user, ['read_at' => now()->subDays(5)]);
    $recentUnread = makeNotification($user, ['created_at' => now()->subDays(100)]);
    $ancientUnread = makeNotification($user, ['created_at' => now()->subDays(400)]);

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(DatabaseNotification::pluck('id')->all())->toEqualCanonicalizing([$recentRead->id, $recentUnread->id])
        ->and(DatabaseNotification::find($oldRead->id))->toBeNull()
        ->and(DatabaseNotification::find($ancientUnread->id))->toBeNull();
});
