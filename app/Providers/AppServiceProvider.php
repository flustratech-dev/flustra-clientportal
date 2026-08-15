<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Email pemulihan kata sandi dalam bahasa Indonesia.
         *
         * Notifikasi bawaan Laravel berbahasa Inggris dan menyebut "this
         * password reset link will expire in 60 minutes". Seluruh antarmuka
         * portal ini berbahasa Indonesia; satu email berbahasa Inggris di
         * tengahnya membuat penerimanya ragu apakah emailnya asli — dan ragu
         * pada email yang meminta mengubah kata sandi adalah hal yang tepat
         * untuk dihindari.
         */
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $tautan = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Atur ulang kata sandi Portal Klien Flustra')
                ->greeting('Halo '.($notifiable->name ?? '').',')
                ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun Portal Klien Anda.')
                ->action('Atur Ulang Kata Sandi', url($tautan))
                ->line('Tautan ini berlaku '.config('auth.passwords.users.expire', 60).' menit.')
                ->line('Bila Anda tidak meminta ini, abaikan saja email ini — kata sandi Anda tidak berubah.')
                ->salutation('— Tim Flustra');
        });
    }
}
