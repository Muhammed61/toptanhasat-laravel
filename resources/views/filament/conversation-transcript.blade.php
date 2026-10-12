{{--
    Admin panelinde alıcı–satıcı konuşmasının sohbet görünümü.
    Alıcı solda (gri), satıcı sağda (yeşil). Her mesajın yanında silme düğmesi
    ViewConversation::deleteMessage() çağırır.
--}}
@php
    /** @var \App\Models\Conversation $conversation */
    $conversation = $getRecord();
    $messages = $conversation->messages()->with('sender')->orderBy('id')->get();
    $lastDay = null;
@endphp

<div class="th-chat" x-data x-init="$el.scrollTop = $el.scrollHeight">
    @forelse ($messages as $message)
        @php
            $isSeller = $message->sender_id === $conversation->seller_id;
            $day = $message->created_at->locale('tr')->isoFormat('D MMMM YYYY');
        @endphp

        @if ($day !== $lastDay)
            <div class="th-chat-day">{{ $message->created_at->isToday() ? 'Bugün' : ($message->created_at->isYesterday() ? 'Dün' : $day) }}</div>
            @php $lastDay = $day; @endphp
        @endif

        <div @class(['th-chat-row', 'th-chat-row--seller' => $isSeller]) wire:key="msg-{{ $message->id }}">
            <div class="th-chat-bubble">
                <div class="th-chat-meta">
                    <strong>{{ $message->sender?->fullName() ?? 'Silinmiş üye' }}</strong>
                    <span>· {{ $isSeller ? 'Satıcı' : 'Alıcı' }}</span>
                </div>
                <div class="th-chat-body">{{ $message->body }}</div>
                <div class="th-chat-time">{{ $message->created_at->format('H:i') }}</div>
            </div>
            <button
                type="button"
                class="th-chat-delete"
                wire:click="deleteMessage({{ $message->id }})"
                wire:confirm="Bu mesaj iki taraftan da silinsin mi?"
                x-tooltip="{ content: 'Mesajı sil', theme: $store.theme }"
                aria-label="Mesajı sil"
            >
                {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedTrash) }}
            </button>
        </div>
    @empty
        <p class="th-chat-empty">Bu konuşmada mesaj kalmadı.</p>
    @endforelse
</div>

<style>
    .th-chat {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        max-height: 70vh;
        overflow-y: auto;
        padding: 0.25rem 0.25rem 0.5rem;
    }
    .th-chat-day {
        align-self: center;
        margin: 0.5rem 0 0.25rem;
        padding: 0.15rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        color: var(--gray-500);
        background: var(--gray-100);
    }
    .th-chat-row {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        max-width: 80%;
    }
    .th-chat-row--seller {
        align-self: flex-end;
        flex-direction: row-reverse;
    }
    .th-chat-bubble {
        padding: 0.5rem 0.75rem;
        border-radius: 0.875rem;
        border-bottom-left-radius: 0.25rem;
        background: var(--gray-100);
        color: var(--gray-900);
        font-size: 0.875rem;
        min-width: 0;
    }
    .th-chat-row--seller .th-chat-bubble {
        border-bottom-left-radius: 0.875rem;
        border-bottom-right-radius: 0.25rem;
        background: var(--primary-50);
    }
    .th-chat-meta {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-bottom: 0.125rem;
    }
    .th-chat-meta strong {
        color: var(--gray-700);
        font-weight: 600;
    }
    .th-chat-row--seller .th-chat-meta strong {
        color: var(--primary-700);
    }
    .th-chat-body {
        white-space: pre-line;
        overflow-wrap: anywhere;
    }
    .th-chat-time {
        text-align: right;
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 0.125rem;
    }
    .th-chat-delete {
        flex-shrink: 0;
        padding: 0.3rem;
        border-radius: 0.5rem;
        color: var(--gray-400);
        opacity: 0;
        transition: opacity 0.15s;
    }
    .th-chat-row:hover .th-chat-delete,
    .th-chat-delete:focus-visible {
        opacity: 1;
    }
    .th-chat-delete:hover {
        color: var(--danger-600);
        background: var(--danger-50);
    }
    .th-chat-delete svg {
        width: 1rem;
        height: 1rem;
    }
    .th-chat-empty {
        font-size: 0.875rem;
        color: var(--gray-500);
    }

    .dark .th-chat-day,
    .dark .th-chat-bubble {
        background: color-mix(in oklab, var(--color-white) 6%, transparent);
        color: var(--gray-100);
    }
    .dark .th-chat-row--seller .th-chat-bubble {
        background: color-mix(in oklab, var(--primary-500) 22%, transparent);
    }
    .dark .th-chat-meta strong {
        color: var(--gray-200);
    }
    .dark .th-chat-row--seller .th-chat-meta strong {
        color: var(--primary-300);
    }
    .dark .th-chat-delete:hover {
        background: color-mix(in oklab, var(--danger-500) 15%, transparent);
        color: var(--danger-400);
    }

    /* Dokunmatik ekranda hover yok: silme düğmesi hep görünsün */
    @media (hover: none) {
        .th-chat-delete { opacity: 1; }
    }
</style>
