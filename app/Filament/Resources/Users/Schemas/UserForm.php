<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\CksStatus;
use App\Models\User;
use App\Support\Phone;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
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

                Section::make('Kimlik ve ÇKS')
                    ->description('Satılık ilan için ÇKS belgesi onaylı olmalı. Belgedeki ad soyad ve TC bu üyeyle aynı mı bakın; '
                        .'belgenin gerçekliğini turkiye.gov.tr → "Barkodlu Belge Doğrulama" ile kontrol edebilirsiniz.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('tc_kimlik')->label('TC kimlik no')
                            ->state(fn (User $record) => $record->tc_kimlik)
                            ->placeholder('Girilmemiş')
                            ->copyable(),
                        TextEntry::make('cks_number')->label('ÇKS no')->placeholder('Gönderilmemiş')->copyable(),
                        TextEntry::make('cks_status')->label('ÇKS durumu')->badge()->placeholder('Gönderilmemiş')
                            ->helperText(fn (User $record) => match (true) {
                                $record->cks_status === CksStatus::Rejected => 'Sebep: '.$record->cks_rejection_reason,
                                $record->cks_submitted_at !== null => 'Gönderildi: '.$record->cks_submitted_at->format('d.m.Y H:i'),
                                default => null,
                            }),
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
