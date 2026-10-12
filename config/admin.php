<?php

/*
| İlk admin hesabı (database/seeders/AdminSeeder → php artisan db:seed --force).
| .env'de ADMIN_* yoksa aşağıdaki varsayılanlar kullanılır. Canlıda ilk girişten
| sonra şifreyi admin panelden (Profil ayarları) değiştirin.
*/

return [
    'email' => env('ADMIN_EMAIL', 'admin@toptanhasat.com'),
    'password' => env('ADMIN_PASSWORD', 'password'),
    'first_name' => env('ADMIN_FIRST_NAME', 'Site'),
    'last_name' => env('ADMIN_LAST_NAME', 'Yöneticisi'),
    'phone' => env('ADMIN_PHONE', '05000000000'),
];
