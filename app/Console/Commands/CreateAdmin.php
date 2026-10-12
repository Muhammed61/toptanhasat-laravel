<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Admin hesabı oluşturur ya da var olan bir üyeyi admin yapar.
 *   php artisan admin:create
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Admin paneline (/admin) erişebilen bir hesap oluşturur veya mevcut üyeyi admin yapar';

    public function handle(): int
    {
        $email = mb_strtolower(text('E-posta', required: true, validate: fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Geçerli bir e-posta girin.'));

        if ($user = User::where('email', $email)->first()) {
            if ($user->is_admin) {
                $this->info("{$user->fullName()} zaten admin.");

                return self::SUCCESS;
            }
            if (! confirm("{$user->fullName()} adlı üye var. Admin yapılsın mı?")) {
                return self::FAILURE;
            }
            $user->forceFill(['is_admin' => true])->save();
            $this->info("{$user->fullName()} artık admin.");

            return self::SUCCESS;
        }

        $user = new User([
            'first_name' => text('Ad', required: true),
            'last_name' => text('Soyad', required: true),
            'email' => $email,
            'phone' => Phone::normalize(text('Cep telefonu', placeholder: '0555 123 45 67', required: true, validate: fn ($v) => match (true) {
                Phone::normalize($v) === null => 'Geçerli bir cep telefonu girin.',
                User::where('phone', Phone::normalize($v))->exists() => 'Bu telefonla kayıtlı bir üye var.',
                default => null,
            })),
            'password' => password('Şifre (en az 8 karakter)', required: true, validate: fn ($v) => mb_strlen($v) < 8 ? 'Şifre en az 8 karakter olmalı.' : null),
            'terms_accepted_at' => now(),
        ]);
        $user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

        $this->info("Admin oluşturuldu. Giriş: ".url('/admin'));

        return self::SUCCESS;
    }
}
