<?php

namespace App\Filament\Resources\ForumTopics;

use App\Filament\Resources\ForumTopics\Pages\ListForumTopics;
use App\Filament\Resources\ForumTopics\Pages\ViewForumTopic;
use App\Filament\Resources\Users\UserResource;
use App\Models\Category;
use App\Models\ForumTopic;
use App\Support\AdminNotifier;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Forum: üyelerin kategorilere göre açtığı konular; onay yok, uygunsuz konu/cevap buradan silinir. */
class ForumTopicResource extends Resource
{
    protected static ?string $model = ForumTopic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleOvalLeftEllipsis;

    protected static ?string $navigationLabel = 'Forum';

    protected static string|UnitEnum|null $navigationGroup = 'Mesajlar';

    protected static ?string $modelLabel = 'Forum konusu';

    protected static ?string $pluralModelLabel = 'Forum';

    protected static ?string $slug = 'forum';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $new = AdminNotifier::unreadCount(AdminNotifier::TYPE_FORUM);

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Yeni konu / cevap';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'user']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Konu ve cevaplar')
                    ->columnSpan(2)
                    ->schema([
                        ViewEntry::make('thread')->hiddenLabel()->view('filament.forum-thread'),
                    ]),
                Section::make('Bilgiler')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('category.name')->label('Kategori')->placeholder('-'),
                        TextEntry::make('user')->label('Açan')
                            ->state(fn (ForumTopic $record) => $record->authorName())
                            ->helperText(fn (ForumTopic $record) => $record->user_id ? null : 'Misafir · '.$record->ip_address)
                            ->url(fn (ForumTopic $record) => $record->user ? UserResource::getUrl('edit', ['record' => $record->user]) : null)
                            ->color('primary'),
                        TextEntry::make('replies_count')->label('Cevap sayısı'),
                        TextEntry::make('created_at')->label('Açıldı')->dateTime('d.m.Y H:i'),
                        TextEntry::make('last_activity_at')->label('Son hareket')->dateTime('d.m.Y H:i'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Konu')
                    ->description(fn (ForumTopic $record) => str($record->body)->limit(120))
                    ->wrap()->searchable(),
                TextColumn::make('category.name')->label('Kategori')->badge()->color('gray')->placeholder('-'),
                TextColumn::make('user')->label('Açan')
                    ->state(fn (ForumTopic $record) => $record->authorName())
                    ->description(fn (ForumTopic $record) => $record->user_id ? 'Üye' : 'Misafir · '.$record->ip_address),
                TextColumn::make('replies_count')->label('Cevap')->alignCenter()->sortable(),
                TextColumn::make('last_activity_at')->label('Son hareket')->since()->dateTimeTooltip('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Kategori')
                    ->options(fn () => Category::query()->orderBy('sort')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Görüntüle'),
                DeleteAction::make()->label('Sil')->modalHeading('Konu silinsin mi?')
                    ->modalDescription('Konu ve tüm cevapları silinir. Bu işlem geri alınamaz.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Henüz forum konusu yok')
            ->emptyStateDescription('Üyeler sitede konu açtığında burada görünür.')
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleOvalLeftEllipsis);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForumTopics::route('/'),
            'view' => ViewForumTopic::route('/{record}'),
        ];
    }
}
