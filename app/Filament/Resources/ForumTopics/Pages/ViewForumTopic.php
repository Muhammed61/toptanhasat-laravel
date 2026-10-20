<?php

namespace App\Filament\Resources\ForumTopics\Pages;

use App\Filament\Resources\ForumTopics\ForumTopicResource;
use App\Models\ForumTopic;
use App\Support\AdminNotifier;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewForumTopic extends ViewRecord
{
    protected static string $resource = ForumTopicResource::class;

    /** Konu açılınca bu konunun bildirimleri okundu sayılır */
    public function mount(int|string $record): void
    {
        parent::mount($record);
        AdminNotifier::markSeen(AdminNotifier::TYPE_FORUM, 'forum:'.$this->getRecord()->getKey());
    }

    public function getTitle(): string
    {
        return $this->getRecord()->title;
    }

    /** Konu görünümündeki çöp kutusu düğmesi */
    public function deleteReply(int $id): void
    {
        /** @var ForumTopic $topic */
        $topic = $this->getRecord();
        $reply = $topic->replies()->find($id);

        if ($reply) {
            $reply->delete();
            Notification::make()->title('Cevap silindi')->success()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOnSite')
                ->label('Sitede gör')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => rtrim(config('app.frontend_url'), '/').'/forum/'.$this->getRecord()->getKey(), shouldOpenInNewTab: true),
            DeleteAction::make()->label('Konuyu sil')
                ->modalDescription('Konu ve tüm cevapları silinir. Bu işlem geri alınamaz.'),
        ];
    }
}
