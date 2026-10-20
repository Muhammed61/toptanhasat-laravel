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
use App\Support\MailSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Forum: kategorilere göre konular ve cevaplar. Herkes okur; konu açmak ve cevap yazmak üyelik ister.
 * Onay yok (yorumlar gibi); bağlantı/e-posta içeren metin kabul edilmez, uygunsuz olanlar panelden silinir.
 */
class ForumController extends Controller
{
    public const PER_PAGE = 10;

    public const REPLIES_PER_PAGE = 30;

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
        $user = $request->user();
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

        $title = trim($data['title']);
        $duplicate = ForumTopic::where('user_id', $user->id)->where('created_at', '>=', now()->subDay())
            ->pluck('title')->contains(fn (string $t) => self::normalize($t) === self::normalize($title));
        if ($duplicate) {
            throw ValidationException::withMessages(['title' => 'Bu başlıkla zaten bir konu açtınız.']);
        }

        $topic = new ForumTopic([
            'category_id' => Category::where('slug', $data['category'])->value('id'),
            'title' => $title,
            'body' => trim($data['body']),
        ]);
        $topic->user()->associate($user)->save();
        $topic->load(['category', 'user']);

        rescue(fn () => AdminNotifier::forum($topic));
        if (! $user->is_admin) {
            AdminMail::forum($topic);
        }

        return response()->json($this->presentTopic($topic), 201);
    }

    public function reply(Request $request, ForumTopic $topic): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:1000', new NoLinks],
        ], [
            'body.required' => 'Cevabınızı yazın.',
            'body.min' => 'Cevap çok kısa.',
            'body.max' => 'Cevap en fazla 1000 karakter olabilir.',
        ]);

        $body = trim($data['body']);
        $duplicate = $topic->replies()->where('user_id', $user->id)->where('created_at', '>=', now()->subDay())
            ->pluck('body')->contains(fn (string $b) => self::normalize($b) === self::normalize($body));
        if ($duplicate) {
            throw ValidationException::withMessages(['body' => 'Bu cevabı zaten yazdınız.']);
        }

        $reply = new ForumReply(['body' => $body]);
        $reply->user()->associate($user);
        $topic->replies()->save($reply);
        $reply->setRelation('topic', $topic);

        rescue(fn () => AdminNotifier::forum($topic, $reply));
        if (! $user->is_admin) {
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

        $participants = User::query()
            ->whereIn('id', $topic->replies()->whereKeyNot($reply->id)->select('user_id'))
            ->whereKeyNot(array_filter([$authorId, $ownerId]))
            ->get();
        $owner = $ownerId !== $authorId ? $topic->user : null;

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
            'author' => $this->presentAuthor($t->user),
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
            'author' => $this->presentAuthor($r->user),
            'is_topic_owner' => $r->user_id === $topic->user_id,
            'created_at' => $r->created_at->toIso8601String(),
        ];
    }

    private function presentAuthor(?User $user): array
    {
        return ['name' => $user?->fullName() ?? 'Silinmiş üye', 'avatar_url' => $user?->avatarUrl()];
    }

    /** Karşılaştırma için: Türkçe küçük harf, fazla boşluk tek boşluk */
    private static function normalize(string $text): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], trim($text))));
    }
}
