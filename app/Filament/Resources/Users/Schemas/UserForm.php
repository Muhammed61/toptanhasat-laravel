<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Support\Phone;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Üye bilgileri')
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')->label('Ad')->required()->maxLength(50),
                        TextInput::make('last_name')->label('Soyad')->required()->maxLength(50),
                        TextInput::make('email')->label('E-posta')->email()->required()->unique(ignoreRecord: true),
                        TextInput::make('phone')->label('Cep telefonu')->tel()->required()
                            ->dehydrateStateUsing(fn (?string $state) => Phone::normalize($state) ?? $state)
                            ->rule('regex:/^(\+?90|0)?\s*5[\d\s]{9,12}$/')
                            ->validationMessages(['regex' => 'Geçerli bir cep telefonu girin.'])
                            ->unique(ignoreRecord: true),
                    ]),

                Section::make('Yetki')
                    ->schema([
                        Toggle::make('is_admin')
                            ->label('Admin paneline erişebilir')
                            ->helperText(fn (?User $record) => $record?->is(auth()->user())
                                ? 'Kendi admin yetkinizi kaldıramazsınız.'
                                : 'Açarsanız bu üye tüm ilanları ve üyeleri yönetebilir.')
                            ->disabled(fn (?User $record) => $record?->is(auth()->user())),
                    ]),
            ]);
    }
}
