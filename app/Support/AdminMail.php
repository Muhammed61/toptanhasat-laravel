<?php

namespace App\Support;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\ForumTopics\ForumTopicResource;
use App\Filament\Resources\ListingComments\ListingCommentResource;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Users\UserResource;
use App\Mail\AdminAlert;
use App\Models\Conversation;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Listing;
use App\Models\ListingComment;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sitede olan biten her şey için yöneticiye mail: yeni üye, yeni ilan, ilan güncellemesi,
 * yorum, yeni mesajlaşma, iletişim formu. Mailler Site Ayarları → SMTP Ayarları'ndaki
 * "Siteden Gelen Mailler (Alıcı)" adresine gider; hangi olaylarda gideceği aynı sekmeden seçilir.
 */
class AdminMail
{
    public const EVENTS = [
        'user' => 'Yeni üye',
        'listing' => 'Yeni ilan (onay bekliyor)',
        'listing_updated' => 'İlan güncellendi (tekrar onay bekliyor)',
        'comment' => 'Yeni yorum',
        'conversation' => 'Yeni mesajlaşma başladı',
        'contact' => 'İletişim formu mesajı',
        'forum' => 'Forum (yeni konu ve cevap)',
    ];

    /** Panelde seçim hiç kaydedilmediyse hepsi açık */
    public static function enabled(string $event): bool
    {
        $selected = data_get(SiteSetting::get('mail', []), 'admin_alerts');

        return in_array($event, is_array($selected) ? $selected : array_keys(self::EVENTS), true);
    }

    public static function recipient(): string
    {
        return MailSettings::get()['recipient'];
    }

    public static function newUser(User $user): void
    {
        self::send('user', "Yeni üye: {$user->fullName()}", 'Siteye yeni bir üye katıldı.', [
            'Ad soyad' => $user->fullName(),
            'E-posta' => $user->email,
            'Telefon' => Phone::display($user->phone),
        ], 'Üyeyi görüntüle', UserResource::getUrl('edit', ['record' => $user], isAbsolute: false));
    }

    public static function newListing(Listing $listing, bool $updated = false): void
    {
        $listing->loadMissing(['user', 'city', 'district']);

        self::send(
            $updated ? 'listing_updated' : 'listing',
            ($updated ? 'İlan güncellendi, onay bekliyor: ' : 'Yeni ilan, onay bekliyor: ').$listing->title(),
            $updated
                ? 'Bir ilan sahibi ilanını düzenledi; ilan tekrar onayınızı bekliyor.'
                : 'Yeni bir ilan verildi ve onayınızı bekliyor.',
            [
                'İlan' => $listing->title(),
                'Tür' => $listing->type?->getLabel() ?? '-',
                'Konum' => trim("{$listing->district?->name}, {$listing->city?->name}", ', '),
                'İlan sahibi' => $listing->user?->fullName() ?? '-',
            ],
            'İlanı incele',
            ListingResource::getUrl('view', ['record' => $listing], isAbsolute: false),
        );
    }

    public static function comment(ListingComment $comment): void
    {
        $comment->loadMissing('listing');

        self::send('comment', "Yeni yorum: {$comment->name}", 'Bir ilana yeni yorum yazıldı.', [
            'Yazan' => $comment->name.($comment->user_id ? '' : ' (misafir)'),
            'İlan' => $comment->listing?->title() ?? '-',
            'Yorum' => Str::limit($comment->body, 500),
        ], 'Yorumları görüntüle', ListingCommentResource::getUrl('index', ['filters' => ['listing' => ['value' => $comment->listing_id]]], isAbsolute: false));
    }

    public static function conversation(Conversation $conversation, User $sender, string $body): void
    {
        self::send('conversation', "Yeni mesajlaşma: {$conversation->listingTitle()}", 'Bir üye ilan sahibine ilk mesajını gönderdi.', [
            'Gönderen' => $sender->fullName(),
            'İlan' => $conversation->listingTitle(),
            'Mesaj' => Str::limit($body, 500),
        ], 'Konuşmayı görüntüle', ConversationResource::getUrl('view', ['record' => $conversation], isAbsolute: false));
    }

    public static function forum(ForumTopic $topic, ?ForumReply $reply = null): void
    {
        $topic->loadMissing(['user', 'category']);
        $author = $reply ? $reply->user : $topic->user;

        self::send(
            'forum',
            ($reply ? 'Forumda yeni cevap: ' : 'Yeni forum konusu: ').$topic->title,
            $reply ? 'Bir forum konusuna yeni cevap yazıldı.' : 'Forumda yeni bir konu açıldı.',
            array_filter([
                'Konu' => $topic->title,
                'Kategori' => $topic->category?->name ?? '-',
                'Yazan' => $author?->fullName() ?? '-',
                'Metin' => Str::limit($reply ? $reply->body : $topic->body, 500),
            ]),
            'Konuyu görüntüle',
            ForumTopicResource::getUrl('view', ['record' => $topic], isAbsolute: false),
        );
    }

    /** İletişim formu maili ayrı (ContactMessageReceived); yalnızca seçime bakılır */
    public static function contactEnabled(): bool
    {
        return self::enabled('contact');
    }

    /** @param  array<string, string|null>  $rows */
    private static function send(string $event, string $subject, string $intro, array $rows, string $actionText, string $adminPath): void
    {
        $recipient = self::recipient();
        if (blank($recipient) || ! self::enabled($event)) {
            return;
        }

        // Panel bağlantısı API adresinden kurulur (istek siteden/proxy'den gelse de doğru adres)
        $url = rtrim(config('app.url'), '/').$adminPath;

        MailSettings::sendLater(fn () => Mail::to($recipient)->send(new AdminAlert($subject, $intro, $rows, $actionText, $url)));
    }

    /** @return list<string> */
    public static function defaultEvents(): array
    {
        return array_keys(self::EVENTS);
    }
}
