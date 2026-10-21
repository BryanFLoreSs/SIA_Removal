<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CityPulseResetPassword extends Notification
{
    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $query = http_build_query(['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $url = rtrim((string) config('app.frontend_url'), '/') . '/reset-password?' . $query;

        return (new MailMessage)
            ->subject('Reset your CityPulse password')
            ->line('We received a request to reset the password for your CityPulse account.')
            ->action('Choose a new password', $url)
            ->line('This link expires in ' . config('auth.passwords.users.expire') . ' minutes and can only be used once.')
            ->line('If you did not request this reset, you can ignore this email.');
    }
}
