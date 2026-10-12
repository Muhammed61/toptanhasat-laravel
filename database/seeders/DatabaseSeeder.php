<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // AdminSeeder: .env'de ADMIN_EMAIL / ADMIN_PASSWORD varsa ilk admin hesabı
        $this->call([CitySeeder::class, ContentSeeder::class, AdminSeeder::class]);
    }
}
