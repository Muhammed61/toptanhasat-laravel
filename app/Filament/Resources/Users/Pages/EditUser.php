<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('conversations')
                ->label('Mesajlaşmaları')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->url(fn () => ConversationResource::getUrl('index', ['filters' => ['user' => ['value' => $this->getRecord()->getKey()]]])),
            DeleteAction::make()->label('Sil')
                ->modalDescription('Üyenin tüm ilanları da silinir. Bu işlem geri alınamaz.'),
        ];
    }

    /** is_admin toplu atamaya kapalı; burada açıkça yazılır (kendi yetkisi hariç). */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (array_key_exists('is_admin', $data) && $record->isNot(auth()->user())) {
            $record->forceFill(['is_admin' => (bool) $data['is_admin']]);
        }
        unset($data['is_admin']);

        $record->fill($data)->save();

        return $record;
    }
}
