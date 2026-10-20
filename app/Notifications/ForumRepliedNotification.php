<?php

namespace App\Notifications;

use App\Models\ForumReply;
use App\Models\SiteSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Forum konusuna yeni cevap: konuyu açana ve konuya daha önce cevap yazanlara gider
 * (cevabı yazan hariç).
 */
class ForumRepliedNotification extends Notification
{
    public function __construct(public ForumReply $reply, public bool $toOwner) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return rtrim(config('app.frontend_url'), '/')."/forum/{$this->reply->forum_topic_id}#cevaplar";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = SiteSetting::get('site_name', 'Toptanhasat');
        $title = $this->reply->topic->title;
        $author = $this->reply->user?->fullName() ?? 'Bir üye';

        return (new MailMessage)
            ->subject($this->toOwner ? "\"{$title}\" konunuza yeni cevap var" : "Cevap yazdığınız \"{$title}\" konusunda yeni cevap var")
            ->greeting("Merhaba {$notifiable->first_name},")
            ->line($this->toOwner
                ? "Forumda açtığınız \"{$title}\" konusuna {$author} cevap yazdı:"
                : "Cevap yazdığınız \"{$title}\" konusuna {$author} yeni bir cevap yazdı:")
            ->line('"'.Str::limit($this->reply->body, 300).'"')
            ->action('Konuyu görüntüle', $this->url())
            ->salutation("Sevgiler,\n{$site}");
    }
}
