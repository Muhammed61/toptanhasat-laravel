<?php

/*
| İlk admin hesabı (database/seeders/AdminSeeder). Değerler sunucudaki .env'den okunur;
| şifre koda / repoya yazılmaz. Seeder çalıştıktan sonra ADMIN_PASSWORD'ü .env'den
| silip `php artisan config:cache` yapmanız önerilir.
*/

return [
    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
    'first_name' => env('ADMIN_FIRST_NAME', 'Site'),
    'last_name' => env('ADMIN_LAST_NAME', 'Yöneticisi'),
    'phone' => env('ADMIN_PHONE'),
];
