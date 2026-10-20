{{--
    Admin panelinde forum konusu: önce konunun kendisi, altında cevaplar.
    Her cevabın yanındaki çöp kutusu ViewForumTopic::deleteReply() çağırır.
--}}
@php
    /** @var \App\Models\ForumTopic $topic */
    $topic = $getRecord();
    $replies = $topic->replies()->with('user')->get();
@endphp

<div class="th-forum">
    <article class="th-forum-post th-forum-post--topic">
        <div class="th-forum-meta">
            <strong>{{ $topic->user?->fullName() ?? 'Silinmiş üye' }}</strong>
            <span>· konuyu açtı · {{ $topic->created_at->format('d.m.Y H:i') }}</span>
        </div>
        <div class="th-forum-body">{{ $topic->body }}</div>
    </article>

    @forelse ($replies as $reply)
        <article class="th-forum-post" wire:key="reply-{{ $reply->id }}">
            <div class="th-forum-meta">
                <strong>{{ $reply->user?->fullName() ?? 'Silinmiş üye' }}</strong>
                <span>· {{ $reply->created_at->format('d.m.Y H:i') }}</span>
                <button
                    type="button"
                    class="th-forum-delete"
                    wire:click="deleteReply({{ $reply->id }})"
                    wire:confirm="Bu cevap silinsin mi?"
                    aria-label="Cevabı sil"
                >
                    {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedTrash) }}
                </button>
            </div>
            <div class="th-forum-body">{{ $reply->body }}</div>
        </article>
    @empty
        <p class="th-forum-empty">Henüz cevap yok.</p>
    @endforelse
</div>

<style>
    .th-forum { display: flex; flex-direction: column; gap: 0.625rem; }
    .th-forum-post {
        padding: 0.75rem 0.875rem;
        border-radius: 0.75rem;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        font-size: 0.875rem;
    }
    .th-forum-post--topic { background: var(--primary-50); border-color: var(--primary-200); }
    .th-forum-meta { display: flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; color: var(--gray-500); margin-bottom: 0.25rem; }
    .th-forum-meta strong { color: var(--gray-800); font-weight: 600; }
    .th-forum-body { white-space: pre-line; overflow-wrap: anywhere; color: var(--gray-900); }
    .th-forum-delete { margin-left: auto; padding: 0.25rem; border-radius: 0.375rem; color: var(--gray-400); }
    .th-forum-delete:hover { color: var(--danger-600); background: var(--danger-50); }
    .th-forum-delete svg { width: 1rem; height: 1rem; }
    .th-forum-empty { font-size: 0.875rem; color: var(--gray-500); }
    .dark .th-forum-post { background: rgba(255, 255, 255, 0.04); border-color: rgba(255, 255, 255, 0.1); }
    .dark .th-forum-post--topic { background: rgba(45, 106, 79, 0.18); border-color: rgba(45, 106, 79, 0.4); }
    .dark .th-forum-meta strong, .dark .th-forum-body { color: var(--gray-100); }
</style>
