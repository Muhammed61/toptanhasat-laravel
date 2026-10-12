<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    /** Açılan mesaj okundu sayılır */
    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->getRecord()->markAsRead();
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        /** @var ContactMessage $message */
        $message = $this->getRecord();

        return [
            Action::make('reply')
                ->label('E-posta ile yanıtla')
                ->icon('heroicon-o-arrow-uturn-left')
                ->url('mailto:'.$message->email.'?subject='.rawurlencode('Re: '.$message->subjectLabel().' - Toptanhasat')),
            Action::make('markUnread')
                ->label('Okunmadı yap')
                ->color('gray')
                ->action(function () use ($message) {
                    $message->forceFill(['read_at' => null])->save();
                    $this->redirect(ContactMessageResource::getUrl('index'));
                }),
            DeleteAction::make()->label('Sil'),
        ];
    }
}
