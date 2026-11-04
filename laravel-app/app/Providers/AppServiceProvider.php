<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Carbon::setLocale('id');

        ResetPassword::toMailUsing(function ($user, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

            return (new MailMessage)
                ->subject('Reset Password '.config('koperasi.short_name'))
                ->greeting('Halo '.$user->full_name.',')
                ->line('Kami menerima permintaan reset password untuk akun NIK '.$user->nik.'.')
                ->action('Reset Password', $url)
                ->line('Link ini berlaku 60 menit. Abaikan email ini bila Anda tidak memintanya.');
        });
    }
}
