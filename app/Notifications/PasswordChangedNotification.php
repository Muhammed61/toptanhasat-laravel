<?php

namespace App\Notifications;

use App\Models\SiteSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** Şifre değişince (Profilim, şifremi unuttum ya da admin panel) hesap sahibine güvenlik bildirimi */
class PasswordChangedNotification extends Notification
{
    public function __construct(public Carbon $changedAt) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = SiteSetting::get('site_name', 'Toptanhasat');
        $when = $this->changedAt->copy()->timezone(config('app.timezone'))->locale('tr')->isoFormat('D MMMM YYYY, HH:mm');

        return (new MailMessage)
            ->subject("{$site} şifreniz değiştirildi")
            ->greeting("Merhaba {$notifiable->first_name},")
            ->line("{$site} hesabınızın şifresi {$when} tarihinde değiştirildi.")
            ->line('Bu değişikliği siz yaptıysanız başka bir şey yapmanıza gerek yok.')
            ->line('Siz yapmadıysanız hemen aşağıdaki bağlantıdan yeni bir şifre belirleyin ve bizimle iletişime geçin.')
            ->action('Şifremi sıfırla', rtrim(config('app.frontend_url'), '/').'/sifremi-unuttum')
            ->salutation("Sevgiler,\n{$site}");
    }
}
