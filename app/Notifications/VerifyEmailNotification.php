<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification
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
        $url = 'https://app.obscribe.com/verify-email?'
            . http_build_query([
                'token' => $this->token,
                'email' => $notifiable->email,
            ]);

        return (new MailMessage)
            ->subject('Verify your email — Obscribe')
            ->greeting('Welcome to Obscribe! 🔒')
            ->line('Thanks for signing up. Please verify your email address to get started.')
            ->action('Verify Your Email', $url)
            ->line('This link expires in 24 hours.')
            ->line('If you didn\'t create an Obscribe account, you can safely ignore this email.')
            ->salutation('— The Obscribe Team');
    }
}
