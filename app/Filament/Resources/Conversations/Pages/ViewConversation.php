<?php

namespace App\Filament\Resources\Conversations\Pages;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Conversation;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/** Admin konuşmayı okur; tarafların okundu durumuna dokunulmaz */
class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->listingTitle();
    }

    public function getSubheading(): ?string
    {
        /** @var Conversation $c */
        $c = $this->getRecord();

        return ($c->buyer?->fullName() ?? 'Silinmiş üye').' ↔ '.($c->seller?->fullName() ?? 'Silinmiş üye');
    }

    /** Sohbet görünümündeki çöp kutusu düğmesi */
    public function deleteMessage(int $id): void
    {
        /** @var Conversation $c */
        $c = $this->getRecord();
        $message = $c->messages()->find($id);

        if ($message) {
            $c->removeMessage($message);
            Notification::make()->title('Mesaj silindi')->success()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewListing')
                ->label('Sitede ilanı gör')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn () => $this->getRecord()->listing_id !== null)
                ->url(fn () => rtrim(config('app.frontend_url'), '/').'/ilan/'.$this->getRecord()->listing_id, shouldOpenInNewTab: true),
            DeleteAction::make()->label('Konuşmayı sil')
                ->modalDescription('Konuşma ve içindeki tüm mesajlar iki taraftan da silinir. Bu işlem geri alınamaz.'),
        ];
    }
}
