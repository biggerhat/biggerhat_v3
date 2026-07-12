<?php

namespace App\Notifications\Campaign;

use App\Models\Campaign\CampaignInvitation;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an organizer invites an existing user by user_id (not a raw
 * email — an email-only invite has no User to notify).
 */
class CampaignInvitationReceived extends Notification
{
    public function __construct(public CampaignInvitation $invitation) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->invitation->loadMissing(['campaign:id,name,organizer_user_id', 'campaign.organizer:id,name']);

        return [
            'type' => 'campaign_invitation_received',
            'message' => "You've been invited to join the campaign \"{$this->invitation->campaign->name}\".",
            'actor' => ['id' => $this->invitation->campaign->organizer_user_id, 'name' => $this->invitation->campaign->organizer?->name],
            'action_url' => route('campaigns.invitations.show', $this->invitation->token),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'campaign.invitation.received';
    }
}
