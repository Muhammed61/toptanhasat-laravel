<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\CksStatus;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\AdminNotifier;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** Üye sayfası açılınca bu üyenin ÇKS bildirimi okundu sayılır */
    public function mount(int|string $record): void
    {
        parent::mount($record);
        AdminNotifier::markSeen(AdminNotifier::TYPE_CKS, 'cks:'.$this->getRecord()->getKey());
    }

    protected function getHeaderActions(): array
    {
        /** @var User $user */
        $user = $this->getRecord();

        return [
            Action::make('cksDocument')
                ->label('ÇKS belgesini aç')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->visible(fn () => filled($user->cks_document_path))
                ->url(fn () => route('admin.cks-document', $user), shouldOpenInNewTab: true),
            Action::make('approveCks')
                ->label('ÇKS onayla')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible(fn () => $user->cks_status === CksStatus::Pending || $user->cks_status === CksStatus::Rejected)
                ->requiresConfirmation()
                ->modalHeading('ÇKS belgesi onaylansın mı?')
                ->modalDescription(fn () => "Belgedeki ad soyad \"{$user->fullName()}\" ile aynı olmalı. Onaylanınca üyenin satılık ilanları yayına alınabilir ve üyeye mail gider.")
                ->modalSubmitActionLabel('Onayla')
                ->action(function () use ($user) {
                    $user->approveCks();
                    Notification::make()->title('ÇKS belgesi onaylandı')->success()->send();
                    $this->refreshFormData(['cks_status']);
                }),
            Action::make('rejectCks')
                ->label('ÇKS reddet')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn () => $user->cks_status === CksStatus::Pending || $user->cks_status === CksStatus::Approved)
                ->modalHeading('ÇKS belgesi reddedilsin mi?')
                ->modalDescription('Sebep üyeye mail ile gider; üye Profilim sayfasından tekrar gönderebilir.')
                ->schema([
                    Textarea::make('reason')->label('Red sebebi')->required()->maxLength(255)
                        ->placeholder('Örn. Belgedeki ad soyad üyelik bilgileriyle uyuşmuyor.'),
                ])
                ->modalSubmitActionLabel('Reddet')
                ->action(function (array $data) use ($user) {
                    $user->rejectCks($data['reason']);
                    Notification::make()->title('ÇKS belgesi reddedildi')->warning()->send();
                    $this->refreshFormData(['cks_status']);
                }),
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
