<?php

namespace App\Filament\Pages;

use App\Support\AdminNotifier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\On;

/**
 * Tüm yönetim paneli bildirimleri (zildeki pencerede yalnızca son bildirimler görünür):
 * tür/durum filtresi, görüntüle, okundu/okunmadı, sil, tümünü okundu işaretle.
 */
class Notifications extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $navigationLabel = 'Bildirimler';

    protected static ?string $title = 'Bildirimler';

    protected static ?string $slug = 'bildirimler';

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.pages.notifications';

    public static function getNavigationBadge(): ?string
    {
        $count = static::unreadQuery()->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Okunmamış bildirim';
    }

    /** Giriş yapan yöneticinin bildirimleri (Filament biçimindekiler) */
    public static function query(): Builder
    {
        return DatabaseNotification::query()
            ->whereMorphedTo('notifiable', auth()->user())
            ->where('data->format', 'filament');
    }

    private static function unreadQuery(): Builder
    {
        return static::query()->whereNull('read_at');
    }

    /** Menüdeki ve zildeki okunmamış sayısını hemen güncelle */
    private function refreshCounters(): void
    {
        $this->dispatch('refresh-sidebar');
        $this->dispatch('databaseNotificationsSent');
    }

    /** Zil penceresinde okundu/sil yapılınca tablo da yenilensin */
    #[On('admin-notifications-changed')]
    public function refreshTable(): void {}

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAllAsRead')
                ->label('Tümünü okundu işaretle')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('gray')
                ->visible(fn () => static::unreadQuery()->exists())
                ->action(function () {
                    static::unreadQuery()->update(['read_at' => now()]);
                    $this->refreshCounters();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $data = fn (DatabaseNotification $record, string $key, $default = null) => data_get($record->data, $key, $default);

        return $table
            ->query(static::query())
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('title')
                    ->label('Bildirim')
                    ->state(fn (DatabaseNotification $record) => $data($record, 'title'))
                    ->description(fn (DatabaseNotification $record) => $data($record, 'body'))
                    ->icon(fn (DatabaseNotification $record) => $data($record, 'icon'))
                    ->iconColor(fn (DatabaseNotification $record) => $data($record, 'iconColor', 'gray'))
                    ->weight(fn (DatabaseNotification $record) => $record->read_at ? null : 'bold')
                    ->wrap(),
                TextColumn::make('type')
                    ->label('Tür')
                    ->state(fn (DatabaseNotification $record) => AdminNotifier::TYPES[$data($record, 'viewData.type')] ?? 'Diğer')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->since()
                    ->dateTimeTooltip('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('read_at')
                    ->label('Durum')
                    ->state(fn (DatabaseNotification $record) => $record->read_at ? 'Okundu' : 'Yeni')
                    ->badge()
                    ->color(fn (DatabaseNotification $record) => $record->read_at ? 'gray' : 'primary'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options(['unread' => 'Okunmamış', 'read' => 'Okunmuş'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'unread' => $query->whereNull('read_at'),
                        'read' => $query->whereNotNull('read_at'),
                        default => $query,
                    }),
                SelectFilter::make('type')
                    ->label('Tür')
                    ->options(AdminNotifier::TYPES)
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, string $type) => $q->where('data->viewData->type', $type),
                    )),
            ])
            ->recordAction('open')
            ->recordActions([
                Action::make('open')
                    ->label('Görüntüle')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (DatabaseNotification $record) => filled($data($record, 'viewData.url')))
                    ->action(function (DatabaseNotification $record) use ($data) {
                        $record->markAsRead();

                        return redirect($data($record, 'viewData.url'));
                    }),
                // Dar ekranda bildirim metnine yer kalsın: diğer işlemler üç nokta menüsünde
                ActionGroup::make([
                    Action::make('markAsRead')
                        ->label('Okundu')
                        ->icon(Heroicon::OutlinedCheck)
                        ->color('gray')
                        ->visible(fn (DatabaseNotification $record) => $record->read_at === null)
                        ->action(fn (DatabaseNotification $record) => $record->markAsRead())
                        ->after(fn () => $this->refreshCounters()),
                    Action::make('markAsUnread')
                        ->label('Okunmadı')
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->color('gray')
                        ->visible(fn (DatabaseNotification $record) => $record->read_at !== null)
                        ->action(fn (DatabaseNotification $record) => $record->markAsUnread())
                        ->after(fn () => $this->refreshCounters()),
                    DeleteAction::make()->label('Sil')->modalHeading('Bildirimi sil')->after(fn () => $this->refreshCounters()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markAsRead')
                        ->label('Okundu işaretle')
                        ->icon(Heroicon::OutlinedCheck)
                        ->action(fn (Collection $records) => $records->each->markAsRead())
                        ->deselectRecordsAfterCompletion()
                        ->after(fn () => $this->refreshCounters()),
                    DeleteBulkAction::make()->label('Seçilenleri sil')->after(fn () => $this->refreshCounters()),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedBellSlash)
            ->emptyStateHeading('Bildirim yok')
            ->emptyStateDescription('Yeni ilan yorumu, üye mesajı veya iletişim formu mesajı gelince burada görünür.');
    }
}
