<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Database\Seeder;

/**
 * İlk admin hesabını oluşturur (php artisan db:seed --force).
 * Varsayılan: admin@toptanhasat.com / password (config/admin.php; .env'deki ADMIN_* ile değiştirilebilir).
 * - E-posta zaten kayıtlıysa o üye admin yapılır; şifresine dokunulmaz.
 * - Tekrar çalıştırmak güvenlidir; ikinci hesap açmaz, panelden değiştirilen şifreyi geri almaz.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('admin');
        $email = mb_strtolower(trim((string) $config['email']));
        $password = (string) $config['password'];

        if ($email === '' || $password === '') {
            $this->command?->warn('Admin oluşturulmadı: admin e-postası ve şifresi boş (config/admin.php).');

            return;
        }

        if ($user = User::where('email', $email)->first()) {
            if (! $user->is_admin) {
                $user->forceFill(['is_admin' => true])->save();
                $this->command?->info("{$email} admin yapıldı (şifresi değiştirilmedi).");
            } else {
                $this->command?->info("{$email} zaten admin.");
            }

            return;
        }

        $phone = Phone::normalize($config['phone']);
        $problem = match (true) {
            ! filter_var($email, FILTER_VALIDATE_EMAIL) => 'ADMIN_EMAIL geçerli bir e-posta değil.',
            mb_strlen($password) < 8 => 'ADMIN_PASSWORD en az 8 karakter olmalı.',
            $phone === null => 'ADMIN_PHONE geçerli bir cep telefonu değil (örn. 05551234567).',
            User::where('phone', $phone)->exists() => 'ADMIN_PHONE başka bir üyede kayıtlı.',
            default => null,
        };

        if ($problem) {
            $this->command?->error("Admin oluşturulmadı: {$problem}");

            return;
        }

        $user = new User([
            'first_name' => $config['first_name'] ?: 'Site',
            'last_name' => $config['last_name'] ?: 'Yöneticisi',
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'terms_accepted_at' => now(),
        ]);
        $user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

        $this->command?->info("Admin oluşturuldu: {$email}. İlk girişten sonra şifreyi panelden değiştirin.");
    }
}
