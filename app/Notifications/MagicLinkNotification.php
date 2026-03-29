<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLinkNotification extends Notification
{
    use Queueable;

    protected string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = 'https://app.obscribe.com/magic-link/verify?token=' . $this->token;

        return (new MailMessage)
            ->subject('Your magic link — Obscribe')
            ->greeting('Hey! 🔐')
            ->line('You requested a magic link to sign in to Obscribe.')
            ->action('Sign In to Obscribe', $url)
            ->line('This link expires in 15 minutes.')
            ->line('If you didn\'t request this, you can safely ignore this email.')
            ->salutation('— The Obscribe Team');
    }
}
