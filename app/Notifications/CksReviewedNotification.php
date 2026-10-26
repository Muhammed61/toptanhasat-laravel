<?php

namespace App\Notifications;

use App\Models\SiteSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Yönetici ÇKS belgesini onaylayınca ya da reddedince üyeye mail */
class CksReviewedNotification extends Notification
{
    public function __construct(public bool $approved, public ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = SiteSetting::get('site_name', 'Toptanhasat');
        $profile = rtrim(config('app.frontend_url'), '/').'/profil';

        $mail = (new MailMessage)->greeting("Merhaba {$notifiable->first_name},");

        if ($this->approved) {
            return $mail
                ->subject('ÇKS belgeniz onaylandı')
                ->line('Gönderdiğiniz ÇKS belgesi incelendi ve onaylandı. Satılık ilanlarınız kontrol edildikten sonra yayına alınır.')
                ->action('İlan ver', rtrim(config('app.frontend_url'), '/').'/ilan-ver')
                ->salutation("Sevgiler,\n{$site}");
        }

        return $mail
            ->subject('ÇKS belgeniz onaylanmadı')
            ->line('Gönderdiğiniz ÇKS belgesi onaylanmadı.')
            ->line('Sebep: '.$this->reason)
            ->line('Profilim sayfasından bilgilerinizi düzeltip belgeyi tekrar gönderebilirsiniz.')
            ->action('Profilime git', $profile)
            ->salutation("Sevgiler,\n{$site}");
    }
}
