<?php

namespace App\Filament\Resources\ForumTopics\Pages;

use App\Filament\Resources\ForumTopics\ForumTopicResource;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Support\AdminNotifier;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
            Action::make('teamReply')
                ->label('Ekip olarak cevap yaz')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->modalHeading('Ekip olarak cevap yaz')
                ->modalDescription('Cevap sitede "Toptanhasat Ekibi" adıyla ve "Ekip" rozetiyle görünür.')
                ->schema([
                    Textarea::make('body')->label('Cevap')->required()->minLength(2)->maxLength(1000)->rows(5),
                ])
                ->modalSubmitActionLabel('Gönder')
                ->action(function (array $data) {
                    $reply = new ForumReply(['body' => $data['body']]);
                    $reply->user()->associate(auth()->user());
                    $this->getRecord()->replies()->save($reply);
                    $this->getRecord()->refresh();

                    Notification::make()->title('Cevap eklendi')->success()->send();
                }),
            Action::make('teamSubtopic')
                ->label('Ekip olarak alt başlık aç')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->visible(fn () => $this->getRecord()->parent_id === null)
                ->modalHeading('Alt başlık aç')
                ->modalDescription(fn () => '"'.$this->getRecord()->title.'" konusunun içinde açılır; sitede "Toptanhasat Ekibi" adıyla görünür.')
                ->schema([
                    TextInput::make('title')->label('Başlık')->required()->minLength(5)->maxLength(120)
                        ->placeholder('Örn. Çeltik fiyatları'),
                    Textarea::make('body')->label('Metin')->required()->minLength(10)->maxLength(2000)->rows(5),
                ])
                ->modalSubmitActionLabel('Aç')
                ->action(function (array $data) {
                    /** @var ForumTopic $parent */
                    $parent = $this->getRecord();
                    $topic = new ForumTopic([...$data, 'parent_id' => $parent->id, 'category_id' => $parent->category_id]);
                    $topic->user()->associate(auth()->user());
                    $topic->save();

                    Notification::make()->title('Alt başlık açıldı')->success()->send();
                    $this->redirect(ForumTopicResource::getUrl('view', ['record' => $topic]));
                }),
            Action::make('viewOnSite')
                ->label('Sitede gör')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => rtrim(config('app.frontend_url'), '/').'/forum/'.$this->getRecord()->getKey(), shouldOpenInNewTab: true),
            DeleteAction::make()->label('Konuyu sil')
                ->modalDescription('Konu, alt başlıkları ve tüm cevapları silinir. Bu işlem geri alınamaz.'),
        ];
    }
}
