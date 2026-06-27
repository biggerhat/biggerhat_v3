<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * BiggerHat-branded password reset email. Extends the framework notification
 * so the token, reset URL, and expiry handling stay identical — only the
 * subject and copy are customised.
 */
class ResetPasswordNotification extends ResetPassword
{
    /**
     * Build the branded mail representation of the notification.
     */
    protected function buildMailMessage($url): MailMessage
    {
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Reset your BiggerHat password')
            ->greeting('Howdy,')
            ->line('We received a request to reset the password for your BiggerHat account.')
            ->action('Reset Password', $url)
            ->line("This password reset link will expire in {$expire} minutes.")
            ->line('If you did not request a password reset, no further action is required — your account is safe.')
            ->salutation("Happy gaming,\nThe BiggerHat Team");
    }
}
