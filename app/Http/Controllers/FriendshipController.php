<?php

namespace App\Http\Controllers;

use App\Enums\MessageTypeEnum;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\Friend\FriendRequestAccepted;
use App\Notifications\Friend\FriendRequestReceived;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return inertia('Friends/Index', [
            'friends' => $user->friendshipsSent()->accepted()->with('addressee:id,name')->get()
                ->map(fn (Friendship $f) => ['friendship_id' => $f->id, 'user' => $f->addressee])
                ->concat(
                    $user->friendshipsReceived()->accepted()->with('requester:id,name')->get()
                        ->map(fn (Friendship $f) => ['friendship_id' => $f->id, 'user' => $f->requester]),
                )
                ->values(),
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

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
}
