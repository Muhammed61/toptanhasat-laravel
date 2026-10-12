<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Support\Phone;
use Closure;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Admin profil sayfası (sağ üstteki avatar → Profil).
 * Filament'in hazır sayfası yalnızca tek "name" alanı bildiği için bizim
 * ad / soyad / telefon / fotoğraf alanlarımızla yeniden düzenlendi.
 */
class EditProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return 'Profil ayarları';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Profil ayarları';
    }

    public function defaultForm(Schema $schema): Schema
    {
        // Bölümlü düzende etiketler alanların üstünde dursun
        return parent::defaultForm($schema)->inlineLabel(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profil fotoğrafı')
                    ->icon(Heroicon::OutlinedCamera)
                    ->description('Panelin sağ üstündeki yuvarlak resim. Boş bırakırsanız baş harfleriniz görünür.')
                    ->aside()
                    ->schema([
                        FileUpload::make('avatar_path')
                            ->hiddenLabel()
                            ->avatar()
                            ->image()
                            ->imageEditor()
                            ->circleCropper()
                            ->disk('public')
                            ->directory('avatars')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->helperText('En fazla 2 MB, kare fotoğraf önerilir.'),
                    ]),

                Section::make('Kişisel bilgiler')
                    ->icon(Heroicon::OutlinedUser)
                    ->aside()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('first_name')->label('Ad')->required()->maxLength(50),
                            TextInput::make('last_name')->label('Soyad')->required()->maxLength(50),
                        ]),
                        $this->getEmailFormComponent()->label('E-posta'),
                        TextInput::make('phone')->label('Cep telefonu')->required()
                            ->placeholder('0555 123 45 67')
                            // Ekranda okunur biçim (0555 123 45 67), kayıtta 5551234567
                            ->formatStateUsing(fn (?string $state) => Phone::display($state))
                            ->dehydrateStateUsing(fn (?string $state) => Phone::normalize($state) ?? $state)
                            // Biçim ve benzersizlik normalize edilmiş numara (5XXXXXXXXX) üzerinden kontrol edilir
                            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                                $phone = Phone::normalize((string) $value);
                                if ($phone === null) {
                                    $fail('Geçerli bir cep telefonu girin (05XX XXX XX XX).');
                                } elseif (User::where('phone', $phone)->whereKeyNot($this->getUser()->getKey())->exists()) {
                                    $fail('Bu telefon numarası başka bir üyede kayıtlı.');
                                }
                            }),
                    ]),

                Section::make('Şifre değiştir')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->description('Değiştirmek istemiyorsanız boş bırakın.')
                    ->aside()
                    ->schema([
                        $this->getPasswordFormComponent()->label('Yeni şifre'),
                        $this->getPasswordConfirmationFormComponent()->label('Yeni şifre (tekrar)'),
                        $this->getCurrentPasswordFormComponent()->label('Mevcut şifre'),
                    ]),
            ]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Profiliniz güncellendi';
    }
}
