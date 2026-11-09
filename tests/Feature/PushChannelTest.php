<?php

use App\Models\DeviceToken;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\Friend\FriendRequestReceived;
use App\Services\Push\NullPushSender;
use App\Services\Push\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Records what would have been pushed; tokens named "dead-*" report as expired. */
function fakePushSender(): object
{
    $sender = new class implements PushSender
    {
        /** @var array<int, array{token: string, body: string, url: ?string}> */
        public array $sent = [];

        public function send(DeviceToken $token, string $title, string $body, ?string $url): bool
        {
            $this->sent[] = ['token' => $token->token, 'body' => $body, 'url' => $url];

            return ! str_starts_with($token->token, 'dead-');
        }
    };
    app()->instance(PushSender::class, $sender);

    return $sender;
}

function friendRequestFor(User $addressee): FriendRequestReceived
{
    $friendship = Friendship::factory()->create(['addressee_id' => $addressee->id]);

    return new FriendRequestReceived($friendship);
}

it('does nothing while only the null sender is bound', function () {
    expect(app(PushSender::class))->toBeInstanceOf(NullPushSender::class);

    $user = User::factory()->create();
    DeviceToken::factory()->create(['user_id' => $user->id, 'token' => 'abc']);

    $user->notify(friendRequestFor($user));

    expect(DeviceToken::where('token', 'abc')->exists())->toBeTrue()
        ->and($user->notifications()->count())->toBe(1);
});

it('pushes the in-app notification message and link to every device the user registered', function () {
    $sender = fakePushSender();
    $user = User::factory()->create();
    DeviceToken::factory()->ios()->create(['user_id' => $user->id, 'token' => 'phone']);
    DeviceToken::factory()->android()->create(['user_id' => $user->id, 'token' => 'tablet']);
    DeviceToken::factory()->create(['token' => 'someone-else']);

    $user->notify(friendRequestFor($user));

    expect(collect($sender->sent)->pluck('token')->sort()->values()->all())->toBe(['phone', 'tablet'])
        ->and($sender->sent[0]['body'])->toContain('sent you a friend request')
        ->and($sender->sent[0]['url'])->toBe(route('friends.index'));
});

it('drops a token the provider reports as dead', function () {
    fakePushSender();
    $user = User::factory()->create();
    DeviceToken::factory()->create(['user_id' => $user->id, 'token' => 'dead-1']);
    DeviceToken::factory()->create(['user_id' => $user->id, 'token' => 'alive']);

    $user->notify(friendRequestFor($user));

    expect(DeviceToken::pluck('token')->all())->toBe(['alive']);
});

it('respects the user turning push off, without touching the in-app bell', function () {
    $sender = fakePushSender();
    $user = User::factory()->create(['push_notifications' => false]);
    DeviceToken::factory()->create(['user_id' => $user->id]);

    $user->notify(friendRequestFor($user));

    expect($sender->sent)->toBe([])
        ->and($user->notifications()->count())->toBe(1);
});
