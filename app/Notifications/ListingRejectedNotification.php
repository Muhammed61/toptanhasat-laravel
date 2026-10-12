<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Models\SiteSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Admin ilanı reddedince ilan sahibine sebebiyle birlikte mail; düzenleyip tekrar gönderebilir */
class ListingRejectedNotification extends Notification
{
    public function __construct(public Listing $listing) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function editUrl(): string
    {
        return rtrim(config('app.frontend_url'), '/')."/ilanlarim/{$this->listing->id}/duzenle";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = SiteSetting::get('site_name', 'Toptanhasat');
        $title = $this->listing->title();

        return (new MailMessage)
            ->subject("\"{$title}\" ilanınız yayına alınmadı")
            ->greeting("Merhaba {$notifiable->first_name},")
            ->line("\"{$title}\" ilanınız incelendi ancak yayına alınmadı.")
            ->line('Sebep: '.$this->listing->rejection_reason)
            ->action('İlanı düzenle', $this->editUrl())
            ->line('Gerekli düzeltmeleri yapıp kaydettiğinizde ilanınız tekrar incelemeye alınır. Sorunuz olursa iletişim sayfasından bize yazabilirsiniz.')
            ->salutation("Sevgiler,\n{$site}");
    }
}
