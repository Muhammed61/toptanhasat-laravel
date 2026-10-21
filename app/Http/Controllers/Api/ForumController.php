<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use App\Notifications\ForumRepliedNotification;
use App\Rules\NoLinks;
use App\Support\AdminMail;
use App\Support\AdminNotifier;
use App\Support\GuestName;
use App\Support\MailSettings;
use App\Support\SiteData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Forum: kategorilere göre konular ve cevaplar. Herkes okur ve yazar; misafirler IP'ye bağlı
 * "Misafir04217" gibi bir adla görünür (ilan yorumlarındaki gibi). Onay yok; bağlantı/e-posta içeren
 * metin kabul edilmez, misafirlere günlük sınır ve robot tuzağı var, uygunsuz olanlar panelden silinir.
 */
class ForumController extends Controller
{
    public const PER_PAGE = 10;

    public const REPLIES_PER_PAGE = 30;

    /** Misafirin bir IP'den 24 saatte açabileceği konu ve yazabileceği cevap */
    public const GUEST_DAILY_TOPICS = 5;

    public const GUEST_DAILY_REPLIES = 30;

    /** Konular: son hareket edilen üstte; ?kategori=sebze ile filtrelenir, ?adet= ana sayfa için (en çok 10) */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('adet', self::PER_PAGE), 1), self::PER_PAGE);
        $slug = (string) $request->query('kategori', '');

        $page = ForumTopic::query()
            ->with(['category', 'user'])
            ->when($slug !== '', fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->latest('last_activity_at')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ForumTopic $t) => $this->presentTopic($t, excerpt: true))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Formda "… olarak yazıyorsunuz" için (misafir adı ziyaretçinin IP'sinden) */
    public function viewer(Request $request): JsonResponse
    {
        return response()->json(['name' => $this->authorName($request), 'is_member' => $request->user('sanctum') !== null]);
    }

    public function show(Request $request, ForumTopic $topic): JsonResponse
    {
        $topic->load(['category', 'user']);
        $page = $topic->replies()->with('user')->paginate(self::REPLIES_PER_PAGE);

        return response()->json([
            'topic' => $this->presentTopic($topic),
            'replies' => $page->getCollection()->map(fn (ForumReply $r) => $this->presentReply($r, $topic))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $data = $request->validate([
            'category' => ['required', Rule::exists('categories', 'slug')->where('is_active', true)],
            'title' => ['required', 'string', 'min:5', 'max:120', new NoLinks],
            'body' => ['required', 'string', 'min:10', 'max:2000', new NoLinks],
        ], [
            'category.required' => 'Bir kategori seçin.',
            'category.exists' => 'Bir kategori seçin.',
            'title.required' => 'Konu başlığını yazın.',
            'title.min' => 'Başlık çok kısa.',
            'title.max' => 'Başlık en fazla 120 karakter olabilir.',
            'body.required' => 'Konunuzu yazın.',
            'body.min' => 'Biraz daha ayrıntı yazın (en az 10 karakter).',
            'body.max' => 'Metin en fazla 2000 karakter olabilir.',
        ]);

        $this->ensureNotBot($request, 'title');
        $title = trim($data['title']);
        $mine = ForumTopic::query()->where($this->author($request))->where('created_at', '>=', now()->subDay());
        if ((clone $mine)->pluck('title')->contains(fn (string $t) => self::normalize($t) === self::normalize($title))) {
            throw ValidationException::withMessages(['title' => 'Bu başlıkla zaten bir konu açtınız.']);
        }
        if (! $user && $mine->count() >= self::GUEST_DAILY_TOPICS) {
            throw ValidationException::withMessages(['title' => 'Bugün çok fazla konu açtınız. Lütfen yarın tekrar deneyin ya da üye olun.']);
        }

        $topic = new ForumTopic([
            'category_id' => Category::where('slug', $data['category'])->value('id'),
            'title' => $title,
            'body' => trim($data['body']),
        ]);
        $this->stampAuthor($topic, $request)->save();
        $topic->load(['category', 'user']);

        rescue(fn () => AdminNotifier::forum($topic));
        if (! $user?->is_admin) {
            AdminMail::forum($topic);
        }

        return response()->json($this->presentTopic($topic), 201);
    }

    public function reply(Request $request, ForumTopic $topic): JsonResponse
    {
        $user = $request->user('sanctum');
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:1000', new NoLinks],
        ], [
            'body.required' => 'Cevabınızı yazın.',
            'body.min' => 'Cevap çok kısa.',
            'body.max' => 'Cevap en fazla 1000 karakter olabilir.',
        ]);

        $this->ensureNotBot($request, 'body');
        $body = trim($data['body']);
        $duplicate = $topic->replies()->where($this->author($request))->where('created_at', '>=', now()->subDay())
            ->pluck('body')->contains(fn (string $b) => self::normalize($b) === self::normalize($body));
        if ($duplicate) {
            throw ValidationException::withMessages(['body' => 'Bu cevabı zaten yazdınız.']);
        }
        if (! $user && ForumReply::query()->where($this->author($request))->where('created_at', '>=', now()->subDay())->count() >= self::GUEST_DAILY_REPLIES) {
            throw ValidationException::withMessages(['body' => 'Bugün çok fazla cevap yazdınız. Lütfen yarın tekrar deneyin ya da üye olun.']);
        }

        $reply = new ForumReply(['body' => $body]);
        $this->stampAuthor($reply, $request);
        $topic->replies()->save($reply);
        $reply->setRelation('topic', $topic);

        rescue(fn () => AdminNotifier::forum($topic, $reply));
        if (! $user?->is_admin) {
            AdminMail::forum($topic, $reply);
        }
        $this->notifyParticipants($topic, $reply);

        return response()->json($this->presentReply($reply->load('user'), $topic), 201);
    }

    /** Konuyu açana ve önceden cevap yazanlara mail (yazan hariç) */
    private function notifyParticipants(ForumTopic $topic, ForumReply $reply): void
    {
        $authorId = $reply->user_id;
        $ownerId = $topic->user_id;

        // Misafirlerin e-postası yok; yalnızca üyelere gider
        $participants = User::query()
            ->whereIn('id', $topic->replies()->whereKeyNot($reply->id)->whereNotNull('user_id')->select('user_id'))
            ->whereKeyNot(array_filter([$authorId, $ownerId]))
            ->get();
        $owner = $ownerId && $ownerId !== $authorId ? $topic->user : null;

        MailSettings::sendLater(function () use ($reply, $owner, $participants) {
            $owner?->notify(new ForumRepliedNotification($reply, toOwner: true));
            foreach ($participants as $user) {
                $user->notify(new ForumRepliedNotification($reply, toOwner: false));
            }
        });
    }

    private function presentTopic(ForumTopic $t, bool $excerpt = false): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'body' => $excerpt ? Str::limit($t->body, 160) : $t->body,
            'category' => $t->category ? ['key' => $t->category->slug, 'label' => $t->category->name] : null,
            'author' => $this->presentAuthor($t),
            'replies_count' => (int) $t->replies_count,
            'created_at' => $t->created_at->toIso8601String(),
            'last_activity_at' => ($t->last_activity_at ?? $t->created_at)->toIso8601String(),
        ];
    }

    private function presentReply(ForumReply $r, ForumTopic $topic): array
    {
        return [
            'id' => $r->id,
            'body' => $r->body,
            'author' => $this->presentAuthor($r),
            // Misafirde aynı IP'den yazılmışsa da konuyu açan sayılır
            'is_topic_owner' => $r->user_id !== null
                ? $r->user_id === $topic->user_id
                : ($topic->user_id === null && $r->ip_address !== null && $r->ip_address === $topic->ip_address),
            'created_at' => $r->created_at->toIso8601String(),
        ];
    }

    private function presentAuthor(ForumTopic|ForumReply $post): array
    {
        $team = $post->isTeam();

        return [
            'name' => $post->authorName(),
            // Ekip: site ikonu (favicon) ile görünür
            'avatar_url' => $team ? (SiteData::get()['settings']['favicon_url'] ?? null) : $post->user?->avatarUrl(),
            'is_guest' => $post->user_id === null,
            'is_team' => $team,
        ];
    }

    /** Üye: hesabına bağlanır. Misafir: IP'ye bağlı sabit ad ve IP kaydedilir. */
    private function stampAuthor(ForumTopic|ForumReply $post, Request $request): ForumTopic|ForumReply
    {
        $user = $request->user('sanctum');
        if ($user) {
            $post->user()->associate($user);
        } else {
            $post->forceFill(['author_name' => GuestName::for($request->ip())]);
        }
        $post->forceFill(['ip_address' => $request->ip()]);

        return $post;
    }

    /** Aynı kişinin kayıtları: üyede hesap, misafirde IP */
    private function author(Request $request): \Closure
    {
        $user = $request->user('sanctum');

        return fn ($q) => $user ? $q->where('user_id', $user->id) : $q->whereNull('user_id')->where('ip_address', $request->ip());
    }

    private function authorName(Request $request): string
    {
        return $request->user('sanctum')?->fullName() ?? GuestName::for($request->ip());
    }

    /** Gizli alan: insanlar görmez, robotlar doldurur */
    private function ensureNotBot(Request $request, string $field): void
    {
        if (filled($request->input('website'))) {
            throw ValidationException::withMessages([$field => 'Gönderilemedi.']);
        }
    }

    /** Karşılaştırma için: Türkçe küçük harf, fazla boşluk tek boşluk */
    private static function normalize(string $text): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], trim($text))));
    }
}
