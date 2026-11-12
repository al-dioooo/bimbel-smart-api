<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * REQ-014: the reset email, in Indonesian, with a link to the frontend's
 * reset page rather than to an API route (the API has no web pages).
 */
class ResetPasswordNotification extends ResetPassword
{
    protected function resetUrl($notifiable)
    {
        return rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    protected function buildMailMessage($url)
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi '.config('app.name'))
            ->greeting('Halo!')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda.')
            ->action('Atur Ulang Kata Sandi', $url)
            ->line("Tautan ini berlaku selama {$minutes} menit.")
            ->line('Kode reset: '.$this->token)
            ->line('Jika Anda tidak meminta pengaturan ulang kata sandi, abaikan email ini.')
            ->salutation('Salam, '.config('app.name'));
    }
}
