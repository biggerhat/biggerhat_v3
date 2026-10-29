<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells super admins the Discord bot stopped (or resumed) reporting heartbeats.
 */
class BotStatusChanged extends Notification
{
    public function __construct(public readonly bool $online, public readonly ?string $lastSeen = null) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function message(): string
    {
        return $this->online
            ? 'Hat Gamin is back online and reporting heartbeats.'
            : 'Hat Gamin has stopped reporting heartbeats (last seen '.($this->lastSeen ?? 'unknown').').';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->online ? 'Discord bot recovered' : 'Discord bot is offline')
            ->line($this->message())
            ->action('Open Bot Monitor', route('admin.bot_monitor.index'));
    }
}
