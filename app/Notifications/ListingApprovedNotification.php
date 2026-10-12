<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Models\SiteSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Admin ilanı onaylayınca ilan sahibine "ilanınız yayında" maili */
class ListingApprovedNotification extends Notification
{
    public function __construct(public Listing $listing) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/ilan/'.$this->listing->id;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = SiteSetting::get('site_name', 'Toptanhasat');
        $title = $this->listing->title();

        return (new MailMessage)
            ->subject("\"{$title}\" ilanınız yayına alındı")
            ->greeting("Merhaba {$notifiable->first_name},")
            ->line("\"{$title}\" ilanınız incelendi ve yayına alındı. Artık sitede herkes görebilir.")
            ->action('İlanı görüntüle', $this->url())
            ->line('İlanınızı İlanlarım sayfasından düzenleyebilirsiniz; bilgileri değiştirirseniz ilan kontrol edildikten sonra tekrar yayına alınır.')
            ->line("{$site} aracı bir ilan platformudur; fiyat, ödeme ve teslimat alıcı ile satıcı arasında belirlenir.")
            ->salutation("Sevgiler,\n{$site}");
    }
}
