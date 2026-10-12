<?php

namespace App\Filament\Resources\ListingComments;

use App\Filament\Resources\ListingComments\Pages\ListListingComments;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\Listing;
use App\Models\ListingComment;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** İlan detayındaki herkese açık yorumlar; onay yok, uygunsuz olanlar buradan silinir. */
class ListingCommentResource extends Resource
{
    protected static ?string $model = ListingComment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $navigationLabel = 'İlan Yorumları';

    protected static string|UnitEnum|null $navigationGroup = 'Mesajlar';

    protected static ?string $modelLabel = 'Yorum';

    protected static ?string $pluralModelLabel = 'İlan Yorumları';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $today = ListingComment::where('created_at', '>=', today())->count();

        return $today > 0 ? (string) $today : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Bugün yazılan yorumlar';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['listing', 'user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('body')->label('Yorum')
                    ->limit(160)->wrap()
                    ->tooltip(fn (ListingComment $record) => mb_strlen($record->body) > 160 ? $record->body : null)
                    ->searchable(),
                TextColumn::make('name')->label('Yazan')
                    ->description(fn (ListingComment $record) => match (true) {
                        $record->isByListingOwner() => 'İlan sahibi',
                        $record->user_id !== null => 'Üye',
                        default => 'Ziyaretçi · '.$record->ip_address,
                    })
                    ->searchable(),
                TextColumn::make('listing_id')->label('İlan')
                    ->state(fn (ListingComment $record) => $record->listing?->title())
                    ->url(fn (ListingComment $record) => $record->listing ? ListingResource::getUrl('view', ['record' => $record->listing]) : null)
                    ->color('primary'),
                TextColumn::make('created_at')->label('Tarih')->since()->dateTimeTooltip('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('listing')->label('İlan')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Listing::query()
                        ->whereHas('comments')
                        ->where(fn (Builder $q) => $q->where('product', 'like', "%{$search}%")->orWhere('id', ltrim($search, '#')))
                        ->limit(20)->get()
                        ->mapWithKeys(fn (Listing $l) => [$l->id => "#{$l->id} {$l->title()}"])
                        ->all())
                    ->getOptionLabelUsing(fn ($value) => ($l = Listing::find($value)) ? "#{$l->id} {$l->title()}" : null)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $q, $id) => $q->where('listing_id', $id))),
            ])
            ->recordActions([
                DeleteAction::make()->label('Sil')->modalHeading('Yorum silinsin mi?'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Henüz yorum yok')
            ->emptyStateDescription('Ziyaretçiler ilan sayfalarına yorum yazdığında burada görünür.')
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleBottomCenterText);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListListingComments::route('/'),
        ];
    }
}
