<?php

namespace App\Support;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\ForumTopics\ForumTopicResource;
use App\Filament\Resources\ListingComments\ListingCommentResource;
use App\Models\ContactMessage;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\ListingComment;
use App\Models\Message;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * Yönetim paneli bildirimleri (sağ üstteki zil ve Bildirimler sayfası): yeni ilan yorumu,
 * üyeler arası yeni mesaj ve iletişim formu mesajı tüm yöneticilere gider.
 *
 * Kuyruk kullanılmaz (sunucuda kuyruk çalışanı yok); bildirim kayıt anında yazılır.
 * Aynı sohbetteki ardışık mesajlar tek bildirimde toplanır ("3 yeni mesaj"), zil dolmasın.
 * Bağlantılar alan adısız kaydedilir (/admin/...): bildirim siteden gelen bir istekte
 * (örn. geliştirmede localhost:5173 proxy'si) üretilse de panelde doğru adrese gider.
 */
class AdminNotifier
{
    public const TYPE_COMMENT = 'comment';

    public const TYPE_MESSAGE = 'message';

    public const TYPE_CONTACT = 'contact';

    public const TYPE_FORUM = 'forum';

    /** Bildirimler sayfasındaki tür filtresi */
    public const TYPES = [
        self::TYPE_COMMENT => 'İlan yorumu',
        self::TYPE_MESSAGE => 'Üye mesajı',
        self::TYPE_CONTACT => 'İletişim formu',
        self::TYPE_FORUM => 'Forum',
    ];

    public static function comment(ListingComment $comment): void
    {
        $listing = $comment->listing;
        $url = ListingCommentResource::getUrl('index', ['filters' => ['listing' => ['value' => $comment->listing_id]]], isAbsolute: false);

        self::send(
            recipients: self::admins(except: $comment->user_id),
            type: self::TYPE_COMMENT,
            key: "comment:{$comment->id}",
            url: $url,
            notification: fn () => Notification::make()
                ->title("Yeni yorum: {$comment->name}")
                ->body(($listing ? "\"{$listing->title()}\" ilanına: " : '').Str::limit($comment->body, 120))
                ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                ->iconColor('info'),
        );
    }

    public static function message(Message $message): void
    {
        $conversation = $message->conversation;
        $sender = $message->sender;
        $key = "conversation:{$conversation->id}";
        $url = ConversationResource::getUrl('view', ['record' => $conversation], isAbsolute: false);
        $name = $sender?->fullName() ?? 'Üye';

        foreach (self::admins(except: $message->sender_id) as $admin) {
            // Bu sohbetin okunmamış bildirimi varsa sayıyı artırıp en üste taşı
            $existing = self::unread($admin, $key);
            $count = ($existing?->data['viewData']['count'] ?? 0) + 1;

            $notification = Notification::make()
                ->title($count > 1 ? "{$count} yeni mesaj: {$name}" : "Yeni mesaj: {$name}")
                ->body("\"{$conversation->listingTitle()}\" · ".Str::limit($message->body, 120))
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->iconColor('primary');

            self::store($admin, $notification, self::TYPE_MESSAGE, $key, $url, $existing, ['count' => $count]);
        }
    }

    public static function contact(ContactMessage $contact): void
    {
        self::send(
            recipients: self::admins(),
            type: self::TYPE_CONTACT,
            key: "contact:{$contact->id}",
            url: ContactMessageResource::getUrl('view', ['record' => $contact], isAbsolute: false),
            notification: fn () => Notification::make()
                ->title("İletişim formu: {$contact->name}")
                ->body($contact->subjectLabel().' · '.Str::limit($contact->message, 120))
                ->icon(Heroicon::OutlinedEnvelope)
                ->iconColor('warning'),
        );
    }

    /** Yeni forum konusu ya da cevabı (aynı konudaki her olay ayrı bildirim; anahtar konuya göre) */
    public static function forum(ForumTopic $topic, ?ForumReply $reply = null): void
    {
        $post = $reply ?? $topic;
        $name = $post->authorName();

        self::send(
            recipients: self::admins(except: $post->user_id),
            type: self::TYPE_FORUM,
            key: "forum:{$topic->id}",
            url: ForumTopicResource::getUrl('view', ['record' => $topic], isAbsolute: false),
            notification: fn () => Notification::make()
                ->title($reply ? "Forumda yeni cevap: {$name}" : "Yeni forum konusu: {$name}")
                ->body("\"{$topic->title}\"".($reply ? ' · '.Str::limit($reply->body, 120) : ''))
                ->icon(Heroicon::OutlinedChatBubbleOvalLeftEllipsis)
                ->iconColor('success'),
        );
    }

    /** Giriş yapan yöneticinin bu türdeki okunmamış bildirim sayısı (menüdeki rozetler) */
    public static function unreadCount(string $type): int
    {
        return auth()->user()?->unreadNotifications()->where('data->viewData->type', $type)->count() ?? 0;
    }

    /**
     * İlgili sayfa açılınca bildirimler okundu sayılır: liste sayfasında o türün hepsi,
     * kayıt sayfasında yalnızca o kayda ait olan ($key, örn. "conversation:5").
     */
    public static function markSeen(string $type, ?string $key = null): void
    {
        auth()->user()?->unreadNotifications()
            ->where('data->viewData->type', $type)
            ->when($key, fn ($q) => $q->where('data->viewData->key', $key))
            ->update(['read_at' => now()]);
    }

    /** @param  iterable<User>  $recipients */
    private static function send(iterable $recipients, string $type, string $key, string $url, \Closure $notification): void
    {
        foreach ($recipients as $admin) {
            self::store($admin, $notification(), $type, $key, $url);
        }
    }

    private static function store(User $admin, Notification $notification, string $type, string $key, string $url, ?DatabaseNotification $existing = null, array $extra = []): void
    {
        $notification
            ->viewData(['type' => $type, 'key' => $key, 'url' => $url, ...$extra])
            ->actions([
                Action::make('view')->label('Görüntüle')->url($url)->markAsRead(),
                Action::make('markAsRead')->label('Okundu')->color('gray')->markAsRead(),
            ]);

        if ($existing) {
            $existing->forceFill(['data' => $notification->getDatabaseMessage(), 'created_at' => now()])->save();

            return;
        }

        $admin->notifyNow($notification->toDatabase());
    }

    private static function unread(User $admin, string $key): ?DatabaseNotification
    {
        return $admin->unreadNotifications()->where('data->viewData->key', $key)->first();
    }

    /** @return Collection<int, User> */
    private static function admins(?int $except = null)
    {
        return User::query()->where('is_admin', true)->when($except, fn ($q) => $q->whereKeyNot($except))->get();
    }
}
