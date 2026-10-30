<?php

namespace App\Http\Controllers;

use App\Enums\GameStatusEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Requests\Friendship\FriendshipStoreRequest;
use App\Models\Friendship;
use App\Models\Game;
use App\Models\User;
use App\Notifications\Friend\FriendRequestAccepted;
use App\Notifications\Friend\FriendRequestReceived;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $friends = $user->friendshipsSent()->accepted()->with('addressee:id,name,slug')->get()
            ->map(fn (Friendship $f) => ['friendship_id' => $f->id, 'user' => $f->addressee])
            ->concat(
                $user->friendshipsReceived()->accepted()->with('requester:id,name,slug')->get()
                    ->map(fn (Friendship $f) => ['friendship_id' => $f->id, 'user' => $f->requester]),
            )
            ->values();

        $record = $this->headToHead($user, $friends->pluck('user.id')->all());
        $friends = $friends->map(fn (array $f) => $f + ['record' => $record[$f['user']->id] ?? ['games' => 0, 'wins' => 0, 'losses' => 0, 'ties' => 0]]);

        return inertia('Friends/Index', [
            'friends' => $friends,
            'requests_received' => $user->pendingFriendRequestsReceived()
                ->map(fn (Friendship $f) => ['id' => $f->id, 'user' => $f->requester]),
            'requests_sent' => $user->pendingFriendRequestsSent()
                ->map(fn (Friendship $f) => ['id' => $f->id, 'user' => $f->addressee]),
        ]);
    }

    /**
     * JSON list of accepted friends, for quick-invite pickers on other
     * features (Campaign invites, Tournament registration) — avoids
     * re-typing a name into search for someone already on the friend list.
     */
    public function accepted(Request $request)
    {
        return response()->json([
            'friends' => $request->user()->acceptedFriends()->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
        ]);
    }

    public function store(FriendshipStoreRequest $request)
    {
        $data = $request->validated();

        $requester = $request->user();
        $addresseeId = (int) $data['user_id'];

        if ($addresseeId === $requester->id) {
            return redirect()->back()->withMessage('You can\'t friend yourself.', null, MessageTypeEnum::error);
        }

        $existing = Friendship::query()->between($requester->id, $addresseeId)->first();
        if ($existing) {
            return redirect()->back()->withMessage(
                $existing->accepted_at ? 'You\'re already friends.' : 'A request already exists between you two.',
                null,
                MessageTypeEnum::error,
            );
        }

        $friendship = Friendship::create([
            'requester_id' => $requester->id,
            'addressee_id' => $addresseeId,
        ]);

        $addressee = User::find($addresseeId);
        $addressee?->notify(new FriendRequestReceived($friendship));

        return redirect()->back()->withMessage('Friend request sent.');
    }

    public function accept(Request $request, Friendship $friendship)
    {
        abort_unless($friendship->addressee_id === $request->user()->id, 403);

        if (! $friendship->accepted_at) {
            $friendship->update(['accepted_at' => now()]);
            $friendship->requester->notify(new FriendRequestAccepted($friendship));
        }

        return redirect()->back()->withMessage('Friend request accepted.');
    }

    /**
     * Covers both declining a pending request and unfriending — either way
     * it's just deleting the row, mirroring CampaignInvitation::revoke().
     */
    public function destroy(Request $request, Friendship $friendship)
    {
        abort_unless(
            $friendship->requester_id === $request->user()->id || $friendship->addressee_id === $request->user()->id,
            403,
        );

        $friendship->delete();

        return redirect()->back()->withMessage('Removed.');
    }

    /**
     * Completed head-to-head results between the user and each friend, from the user's perspective.
     *
     * @param  array<int, int>  $friendIds
     * @return array<int, array{games: int, wins: int, losses: int, ties: int}>
     */
    private function headToHead(User $user, array $friendIds): array
    {
        if ($friendIds === []) {
            return [];
        }

        $games = Game::query()
            ->where('status', GameStatusEnum::Completed)
            ->where('is_solo', false)
            ->whereHas('players', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('players', fn ($q) => $q->whereIn('user_id', $friendIds))
            ->with('players:id,game_id,user_id')
            ->get(['id', 'is_tie', 'winner_id']);

        $record = [];
        foreach ($games as $game) {
            foreach ($game->players->pluck('user_id')->filter(fn ($id) => $id && in_array($id, $friendIds, true)) as $friendId) {
                $row = $record[$friendId] ??= ['games' => 0, 'wins' => 0, 'losses' => 0, 'ties' => 0];
                $row['games']++;
                if ($game->is_tie || ! $game->winner_id) {
                    $row['ties']++;
                } elseif ($game->winner_id === $user->id) {
                    $row['wins']++;
                } else {
                    $row['losses']++;
                }
                $record[$friendId] = $row;
            }
        }

        return $record;
    }
}
