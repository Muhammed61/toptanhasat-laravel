<?php

namespace App\Notifications;

use App\Models\ListingComment;
use App\Models\SiteSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * İlana yeni yorum yapıldı: ilan sahibine ve o ilana daha önce üye olarak yorum yazanlara gider
 * (yorumu yazan hariç).
 */
class ListingCommentedNotification extends Notification
{
    public function __construct(public ListingComment $comment, public bool $toOwner) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return rtrim(config('app.frontend_url'), '/')."/ilan/{$this->comment->listing_id}#yorumlar";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = SiteSetting::get('site_name', 'Toptanhasat');
        $title = $this->comment->listing->title();

        return (new MailMessage)
            ->subject($this->toOwner ? "\"{$title}\" ilanınıza yeni yorum yapıldı" : "Yorum yaptığınız \"{$title}\" ilanına yeni yorum var")
            ->greeting("Merhaba {$notifiable->first_name},")
            ->line($this->toOwner
                ? "\"{$title}\" ilanınıza {$this->comment->name} yorum yaptı:"
                : "Yorum yaptığınız \"{$title}\" ilanına {$this->comment->name} yeni bir yorum yazdı:")
            ->line('"'.Str::limit($this->comment->body, 300).'"')
            ->action('Yorumu görüntüle', $this->url())
            ->salutation("Sevgiler,\n{$site}");
    }
}
