<?php

namespace App\Filament\Resources\ForumTopics\Pages;

use App\Filament\Resources\ForumTopics\ForumTopicResource;
use App\Support\AdminNotifier;
use Filament\Resources\Pages\ListRecords;

class ListForumTopics extends ListRecords
{
    protected static string $resource = ForumTopicResource::class;

    /** Liste açılınca yeni forum bildirimleri okundu sayılır (menüdeki sayı sıfırlanır) */
    public function mount(): void
    {
        parent::mount();
        AdminNotifier::markSeen(AdminNotifier::TYPE_FORUM);
    }

    public function getSubheading(): ?string
    {
        return 'Üyeler konu açar ve cevap yazar; onay beklemeden yayınlanır. Uygunsuz konu veya cevapları silebilirsiniz.';
    }
}
