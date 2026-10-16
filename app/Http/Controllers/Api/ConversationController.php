<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Support\AdminMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Site içi mesajlaşma (sadece üyeler; konuşmayı yalnızca iki taraf görebilir) */
class ConversationController extends Controller
{
    private const RELATIONS = ['listing', 'buyer', 'seller', 'latestMessage'];

    private const BODY_RULES = ['required', 'string', 'max:2000'];

    private const BODY_MESSAGES = [
        'body.required' => 'Mesajınızı yazın.',
        'body.max' => 'Mesaj en fazla 2000 karakter olabilir.',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = Conversation::forUser($request->user())
            ->whereNotNull('last_message_at')
            ->with(self::RELATIONS)
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        return ConversationResource::collection($conversations);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => Conversation::unreadFor($request->user())->count()]);
    }

    /**
     * Konuşma ve mesajları. `after` verilirse sadece o id'den sonraki mesajlar döner
     * (açık sohbeti birkaç saniyede bir yenilemek için). Açılan konuşma okundu sayılır.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($conversation->hasParticipant($user), 404);

        $after = $request->integer('after');
        $messages = $conversation->messages()
            ->when($after > 0, fn ($q) => $q->where('id', '>', $after))
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values();

        if ($conversation->isUnreadFor($user)) {
            $conversation->markReadBy($user);
        }

        return response()->json([
            'conversation' => new ConversationResource($conversation->load(self::RELATIONS)),
            'messages' => $messages->map(fn (Message $m) => $this->messagePayload($m, $user->id)),
        ]);
    }

    /** İlan sayfasından ilk mesaj: konuşma yoksa açılır, varsa ona eklenir */
    public function start(Request $request, Listing $listing): JsonResponse
    {
        $user = $request->user();
        abort_unless($listing->status === ListingStatus::Approved, 404);
        abort_if($listing->user_id === $user->id, 422, 'Kendi ilanınıza mesaj gönderemezsiniz.');

        $data = $request->validate(['body' => self::BODY_RULES], self::BODY_MESSAGES);

        $conversation = Conversation::firstOrCreate(
            ['listing_id' => $listing->id, 'buyer_id' => $user->id],
            ['seller_id' => $listing->user_id, 'listing_title' => $listing->title()],
        );
        $conversation->addMessage($user, trim($data['body']));

        // Yöneticiye yalnızca yeni başlayan konuşma bildirilir (her mesajda mail gitmez)
        if ($conversation->wasRecentlyCreated) {
            AdminMail::conversation($conversation, $user, trim($data['body']));
        }

        return response()->json(['id' => $conversation->id], 201);
    }

    public function reply(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($conversation->hasParticipant($user), 404);

        $data = $request->validate(['body' => self::BODY_RULES], self::BODY_MESSAGES);
        $message = $conversation->addMessage($user, trim($data['body']));

        return response()->json($this->messagePayload($message, $user->id), 201);
    }

    private function messagePayload(Message $message, int $viewerId): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'mine' => $message->sender_id === $viewerId,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
