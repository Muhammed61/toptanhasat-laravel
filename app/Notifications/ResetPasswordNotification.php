<?php

namespace App\Notifications;

use App\Models\SiteSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Şifremi unuttum" maili; bağlantı Vue sitesindeki yeni şifre sayfasını açar */
class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(object $notifiable): string
    {
        return rtrim(config('app.frontend_url'), '/').'/sifre-sifirla?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = SiteSetting::get('site_name', 'Toptanhasat');
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject("{$site} şifre sıfırlama")
            ->greeting("Merhaba {$notifiable->first_name},")
            ->line("{$site} hesabınız için şifre sıfırlama talebi aldık.")
            ->action('Yeni şifre belirle', $this->url($notifiable))
            ->line("Bu bağlantı {$minutes} dakika geçerlidir ve yalnızca bir kez kullanılabilir.")
            ->line('Bu talebi siz yapmadıysanız bu maili dikkate almayın; şifreniz değişmez.')
            ->salutation("Sevgiler,\n{$site}");
    }
}
