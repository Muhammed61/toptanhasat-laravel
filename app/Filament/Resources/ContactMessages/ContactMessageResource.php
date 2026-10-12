<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'İletişim Formu';

    protected static string|UnitEnum|null $navigationGroup = 'Mesajlar';

    protected static ?string $modelLabel = 'Mesaj';

    protected static ?string $pluralModelLabel = 'İletişim Mesajları';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $unread = ContactMessage::whereNull('read_at')->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Okunmamış mesajlar';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Mesaj')
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('subject')->label('Konu')->badge()
                            ->formatStateUsing(fn (ContactMessage $record) => $record->subjectLabel()),
                        TextEntry::make('message')->hiddenLabel()->prose()->extraAttributes(['style' => 'white-space: pre-line']),
                    ]),
                Section::make('Gönderen')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('name')->label('Ad soyad'),
                        TextEntry::make('email')->label('E-posta')->copyable(),
                        TextEntry::make('phone')->label('Telefon')->copyable()->placeholder('-'),
                        TextEntry::make('created_at')->label('Gönderildi')->dateTime('d.m.Y H:i'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (ContactMessage $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                IconColumn::make('read_at')->label('')
                    ->state(fn (ContactMessage $record) => $record->read_at === null)
                    ->boolean()
                    ->trueIcon(Heroicon::Envelope)->trueColor('primary')
                    ->falseIcon(Heroicon::OutlinedEnvelopeOpen)->falseColor('gray')
                    ->tooltip(fn (ContactMessage $record) => $record->read_at ? 'Okundu' : 'Okunmadı'),
                TextColumn::make('name')->label('Gönderen')
                    ->description(fn (ContactMessage $record) => $record->email)
                    ->weight(fn (ContactMessage $record) => $record->read_at ? null : 'bold')
                    ->searchable(['name', 'email']),
                TextColumn::make('subject')->label('Konu')->badge()->color('gray')
                    ->formatStateUsing(fn (ContactMessage $record) => $record->subjectLabel()),
                TextColumn::make('message')->label('Mesaj')->limit(60)->searchable()->wrap(),
                TextColumn::make('created_at')->label('Tarih')->since()->dateTimeTooltip('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('unread')->label('Durum')
                    ->trueLabel('Okunmamış')->falseLabel('Okunmuş')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('read_at'),
                        false: fn (Builder $q) => $q->whereNotNull('read_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make()->label('Aç'),
                DeleteAction::make()->label('Sil'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markRead')->label('Okundu işaretle')->icon(Heroicon::OutlinedEnvelopeOpen)
                        ->action(fn (Collection $records) => $records->each->markAsRead())
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Henüz mesaj yok')
            ->emptyStateIcon(Heroicon::OutlinedEnvelope);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
