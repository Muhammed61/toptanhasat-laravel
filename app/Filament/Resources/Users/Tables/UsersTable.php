<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('listings'))
            ->columns([
                TextColumn::make('first_name')
                    ->label('Ad soyad')
                    ->formatStateUsing(fn (User $record) => $record->fullName())
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('email')->label('E-posta')->searchable()->copyable(),
                TextColumn::make('phone')->label('Telefon')->searchable()->copyable(),
                TextColumn::make('listings_count')->label('İlan')->sortable()->alignCenter(),
                IconColumn::make('is_admin')->label('Admin')->boolean()->alignCenter(),
                TextColumn::make('created_at')->label('Üyelik')->since()->dateTimeTooltip('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_admin')->label('Admin'),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
                DeleteAction::make()->label('Sil')
                    ->modalDescription('Üyenin tüm ilanları da silinir. Bu işlem geri alınamaz.'),
            ]);
    }
}
