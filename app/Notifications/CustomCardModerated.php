<?php

namespace App\Notifications;

use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a custom card's owner when an admin unpublishes or removes it —
 * CustomCardModerationAdminController previously only flashed a message to
 * the acting admin, leaving the owner with no idea their public card
 * disappeared.
 */
class CustomCardModerated extends Notification
{
    /** @param 'unpublished'|'removed' $action */
    public function __construct(public CustomCharacter|CustomUpgrade $card, public string $action) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $name = $this->card->display_name ?? $this->card->name;

        return [
            'type' => 'custom_card_moderated',
            'message' => "Your custom card \"{$name}\" was {$this->action} by a moderator.",
            'actor' => null,
            'action_url' => route('tools.card_creator.index'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'custom_card.moderated';
    }
}
