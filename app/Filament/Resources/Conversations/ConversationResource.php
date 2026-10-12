<?php

namespace App\Filament\Resources\Conversations;

use App\Filament\Resources\Conversations\Pages\ListConversations;
use App\Filament\Resources\Conversations\Pages\ViewConversation;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
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

/**
 * Alıcı–satıcı mesajlaşmaları. Admin sadece okur (okundu durumunu değiştirmez),
 * gerekirse tek mesajı ya da konuşmanın tamamını siler.
 */
class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Üye Mesajları';

    protected static string|UnitEnum|null $navigationGroup = 'Mesajlar';

    protected static ?string $modelLabel = 'Konuşma';

    protected static ?string $pluralModelLabel = 'Üye Mesajları';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $today = Conversation::where('last_message_at', '>=', today())->count();

        return $today > 0 ? (string) $today : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Bugün mesajlaşılan konuşmalar';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['listing', 'buyer', 'seller', 'latestMessage'])
            ->withCount('messages');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Mesajlar')
                    ->columnSpan(2)
                    ->schema([
                        ViewEntry::make('transcript')->hiddenLabel()
                            ->view('filament.conversation-transcript'),
                    ]),
                Section::make('Bilgiler')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('listing_title')->label('İlan')
                            ->state(fn (Conversation $record) => $record->listingTitle())
                            ->url(fn (Conversation $record) => $record->listing ? ListingResource::getUrl('view', ['record' => $record->listing]) : null)
                            ->color(fn (Conversation $record) => $record->listing ? 'primary' : 'gray')
                            ->helperText(fn (Conversation $record) => $record->listing ? null : 'İlan silinmiş'),
                        self::partyEntry('buyer', 'Alıcı'),
                        self::partyEntry('seller', 'Satıcı (ilan sahibi)'),
                        TextEntry::make('messages_count')->label('Mesaj sayısı')
                            ->state(fn (Conversation $record) => $record->messages()->count()),
                        TextEntry::make('created_at')->label('Başladı')->dateTime('d.m.Y H:i'),
                        TextEntry::make('last_message_at')->label('Son mesaj')->dateTime('d.m.Y H:i')->placeholder('-'),
                    ]),
            ]);
    }

    /** Taraf: ad (üye sayfasına bağlı), altında e-posta ve telefon */
    private static function partyEntry(string $relation, string $label): TextEntry
    {
        return TextEntry::make($relation)->label($label)
            ->state(fn (Conversation $record) => $record->{$relation}?->fullName() ?? 'Silinmiş üye')
            ->url(fn (Conversation $record) => $record->{$relation} ? UserResource::getUrl('edit', ['record' => $record->{$relation}]) : null)
            ->color('primary')
            ->helperText(fn (Conversation $record) => $record->{$relation}
                ? $record->{$relation}->email.' · '.\App\Support\Phone::display($record->{$relation}->phone)
                : null);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_message_at', 'desc')
            ->recordUrl(fn (Conversation $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('listing_title')->label('İlan')
                    ->state(fn (Conversation $record) => $record->listingTitle())
                    ->description(fn (Conversation $record) => $record->listing ? null : 'İlan silinmiş')
                    ->weight('medium')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('buyer_id')->label('Alıcı')
                    ->state(fn (Conversation $record) => $record->buyer?->fullName())
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('buyer', self::userSearch($search))),
                TextColumn::make('seller_id')->label('Satıcı')
                    ->state(fn (Conversation $record) => $record->seller?->fullName())
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('seller', self::userSearch($search))),
                TextColumn::make('latestMessage.body')->label('Son mesaj')
                    ->limit(60)->wrap()->color('gray')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('messages', fn (Builder $m) => $m->where('body', 'like', "%{$search}%"))),
                TextColumn::make('messages_count')->label('Mesaj')->badge()->color('gray')->alignCenter()->sortable(),
                TextColumn::make('last_message_at')->label('Tarih')->since()->dateTimeTooltip('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('user')->label('Üye')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::query()
                        ->where(self::userSearch($search))
                        ->limit(20)->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => "{$u->fullName()} ({$u->email})"])
                        ->all())
                    ->getOptionLabelUsing(fn ($value) => User::find($value)?->fullName())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $q, $id) => $q
                        ->where(fn (Builder $q) => $q->where('buyer_id', $id)->orWhere('seller_id', $id)))),
                SelectFilter::make('listing')->label('İlan')
                    ->searchable()
                    // Sadece mesajlaşma olan ilanlar; başlıkta ya da ilan numarasında arar
                    ->getSearchResultsUsing(fn (string $search) => Conversation::query()
                        ->whereNotNull('listing_id')
                        ->where(fn (Builder $q) => $q->where('listing_title', 'like', "%{$search}%")->orWhere('listing_id', ltrim($search, '#')))
                        ->select('listing_id', 'listing_title')->distinct()->limit(20)->get()
                        ->mapWithKeys(fn (Conversation $c) => [$c->listing_id => "#{$c->listing_id} {$c->listing_title}"])
                        ->all())
                    ->getOptionLabelUsing(fn ($value) => ($l = Listing::find($value)) ? "#{$l->id} {$l->title()}" : null)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $q, $id) => $q->where('listing_id', $id))),
            ])
            ->recordActions([
                ViewAction::make()->label('Aç'),
                DeleteAction::make()->label('Sil')
                    ->modalDescription('Konuşma ve içindeki tüm mesajlar iki taraftan da silinir. Bu işlem geri alınamaz.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Henüz mesajlaşma yok')
            ->emptyStateDescription('Üyeler ilan sayfasından birbirine mesaj gönderdiğinde burada görünür.')
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftRight);
    }

    /** "Ahmet Yılmaz" gibi aramalarda her kelime ad, soyad veya e-postada geçmeli */
    private static function userSearch(string $search): \Closure
    {
        $words = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY);

        return function (Builder $q) use ($words) {
            foreach ($words as $word) {
                $q->where(fn (Builder $q) => $q
                    ->where('first_name', 'like', "%{$word}%")
                    ->orWhere('last_name', 'like', "%{$word}%")
                    ->orWhere('email', 'like', "%{$word}%"));
            }
        };
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
            'view' => ViewConversation::route('/{record}'),
        ];
    }
}
