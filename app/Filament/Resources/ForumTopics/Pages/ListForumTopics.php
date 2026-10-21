<?php

namespace App\Filament\Resources\ForumTopics\Pages;

use App\Filament\Resources\ForumTopics\ForumTopicResource;
use App\Models\Category;
use App\Models\ForumTopic;
use App\Support\AdminNotifier;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

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
        return 'Herkes konu açar ve cevap yazar; onay beklemeden yayınlanır. Uygunsuz konu veya cevapları silebilirsiniz. Yöneticilerin yazdıkları sitede "Ekip" rozetiyle görünür.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newTeamTopic')
                ->label('Ekip olarak konu aç')
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading('Ekip olarak konu aç')
                ->modalDescription('Konu sitede "Toptanhasat Ekibi" adıyla ve "Ekip" rozetiyle görünür.')
                ->schema([
                    Select::make('category_id')->label('Kategori')
                        ->options(fn () => Category::query()->orderBy('sort')->pluck('name', 'id')->all())
                        ->placeholder('Genel (kategorisiz)'),
                    TextInput::make('title')->label('Başlık')->required()->minLength(5)->maxLength(120),
                    Textarea::make('body')->label('Metin')->required()->minLength(10)->maxLength(2000)->rows(6),
                ])
                ->modalSubmitActionLabel('Konuyu aç')
                ->action(function (array $data) {
                    $topic = new ForumTopic($data);
                    $topic->user()->associate(auth()->user());
                    $topic->save();

                    Notification::make()->title('Konu açıldı')->success()->send();
                    $this->redirect(ForumTopicResource::getUrl('view', ['record' => $topic]));
                }),
        ];
    }
}
