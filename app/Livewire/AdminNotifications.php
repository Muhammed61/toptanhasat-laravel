<?php

namespace App\Livewire;

use App\Filament\Pages\Notifications;
use Filament\Livewire\DatabaseNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Livewire\Attributes\On;

/**
 * Sağ üstteki zil penceresi: son bildirimler + "Tümünü okundu işaretle" + "Tüm bildirimler" sayfası.
 * Liste kısa tutulur; eskiler Bildirimler sayfasında (filtre, sayfalama).
 */
class AdminNotifications extends DatabaseNotifications
{
    public const LIMIT = 15;

    public function getNotifications(): DatabaseNotificationCollection
    {
        /** @phpstan-ignore-next-line */
        return $this->getNotificationsQuery()->latest()->limit(self::LIMIT)->get();
    }

    public function getAllNotificationsUrl(): string
    {
        return Notifications::getUrl();
    }

    public function getTotalNotificationsCount(): int
    {
        return $this->getNotificationsQuery()->count();
    }

    // Okundu/sil sonrası menüdeki "Bildirimler" sayısı ve Bildirimler sayfasının tablosu da güncellensin
    // (#[On] üst sınıftakiyle aynı: bildirimdeki düğmeler bu olaylarla çağırır)
    #[On('markedNotificationAsRead')]
    public function markNotificationAsRead(string $id): void
    {
        parent::markNotificationAsRead($id);
        $this->notifyChanged();
    }

    #[On('markedNotificationAsUnread')]
    public function markNotificationAsUnread(string $id): void
    {
        parent::markNotificationAsUnread($id);
        $this->notifyChanged();
    }

    public function markAllNotificationsAsRead(): void
    {
        parent::markAllNotificationsAsRead();
        $this->notifyChanged();
    }

    #[On('notificationClosed')]
    public function removeNotification(string $id): void
    {
        parent::removeNotification($id);
        $this->notifyChanged();
    }

    public function clearNotifications(): void
    {
        parent::clearNotifications();
        $this->notifyChanged();
    }

    private function notifyChanged(): void
    {
        $this->dispatch('refresh-sidebar');
        $this->dispatch('admin-notifications-changed');
    }

    public function render(): View
    {
        return view('livewire.admin-notifications');
    }
}
