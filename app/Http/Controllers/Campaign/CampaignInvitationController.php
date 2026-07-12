<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreInvitationRequest;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignInvitation;
use App\Models\User;
use App\Notifications\Campaign\CampaignInvitationReceived;
use App\Traits\Campaign\AddsCampaignMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignInvitationController extends Controller
{
    use AddsCampaignMember;

    public function store(StoreInvitationRequest $request, Campaign $campaign)
    {
        $data = $request->validated();
        $expiresIn = (int) ($data['expires_in_days'] ?? 14);

        // Block duplicates: if a pending invitation already exists for the
        // same user/email, return that one instead of stacking rows.
        $existing = CampaignInvitation::query()
            ->where('campaign_id', $campaign->id)
            ->when($data['user_id'] ?? null, fn ($q, $uid) => $q->where('user_id', $uid))
            ->when($data['email'] ?? null, fn ($q, $email) => $q->where('email', $email))
            ->pending()
            ->first();

        if ($existing) {
            return redirect()->route('campaigns.show', $campaign)
                ->withMessage('Invitation already pending.');
        }

        // Reject if user is already a player.
        if (! empty($data['user_id']) && $campaign->players()->where('user_id', $data['user_id'])->exists()) {
            return redirect()->back()->withMessage('That user is already in this campaign.', null, MessageTypeEnum::error);
        }

        $invitation = CampaignInvitation::create([
            'campaign_id' => $campaign->id,
            'user_id' => $data['user_id'] ?? null,
            'email' => $data['email'] ?? null,
            'expires_at' => now()->addDays($expiresIn),
        ]);

        // Only an existing-user invite has someone to notify — an
        // email-only invite has no User row yet.
        if ($invitation->user_id) {
            User::find($invitation->user_id)?->notify(new CampaignInvitationReceived($invitation));
        }

        return redirect()->route('campaigns.show', $campaign)->withMessage('Invitation sent.');
    }

    /**
     * Public-facing accept screen, keyed by the invitation token. Visitors
     * must be logged in to accept; if not we punt to login and bounce back.
     */
    public function show(Request $request, CampaignInvitation $invitation)
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if ($invitation->accepted_at) {
            return redirect()->route('campaigns.show', $invitation->campaign_id)
                ->withMessage('You already accepted this invitation.');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            return inertia('Campaigns/Invitations/Expired', ['campaign_name' => $invitation->campaign->name]);
        }

        // If the invitation is keyed to a different user, refuse.
        if ($invitation->user_id && $invitation->user_id !== $request->user()->id) {
            abort(403, 'This invitation is for a different account.');
        }

        return inertia('Campaigns/Invitations/Show', [
            'invitation' => [
                'token' => $invitation->token,
                'campaign' => [
                    'id' => $invitation->campaign->id,
                    'name' => $invitation->campaign->name,
                    'length_weeks' => $invitation->campaign->length_weeks,
                    'status' => $invitation->campaign->status,
                ],
                'inviter' => $invitation->campaign->organizer?->only(['id', 'name']),
                'expires_at' => $invitation->expires_at,
            ],
        ]);
    }

    public function accept(Request $request, CampaignInvitation $invitation)
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if ($invitation->accepted_at) {
            return redirect()->route('campaigns.show', $invitation->campaign_id);
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            return redirect()->back()->withMessage('Invitation expired.', null, MessageTypeEnum::error);
        }

        if ($invitation->user_id && $invitation->user_id !== $request->user()->id) {
            abort(403);
        }

        $campaign = $invitation->campaign;

        if ($campaign->status === CampaignStatusEnum::Ended) {
            return redirect()->back()->withMessage('Campaign has ended.', null, MessageTypeEnum::error);
        }

        DB::transaction(function () use ($invitation, $request, $campaign) {
            // Mark invitation consumed.
            $invitation->update(['accepted_at' => now(), 'user_id' => $request->user()->id]);

            $this->addCampaignMember($campaign, $request->user());
        });

        return redirect()->route('campaigns.show', $invitation->campaign_id)
            ->withMessage('Welcome to the campaign!');
    }

    public function revoke(Request $request, Campaign $campaign, CampaignInvitation $invitation)
    {
        $this->authorize('update', $campaign);

        if ($invitation->campaign_id !== $campaign->id) {
            abort(404);
        }

        $invitation->delete();

        return redirect()->route('campaigns.show', $campaign)->withMessage('Invitation revoked.');
    }
}
