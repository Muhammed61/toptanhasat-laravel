<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitySeeder extends Seeder
{
    /**
     * 81 il ve ilçelerini database/data/cities.json dosyasından yükler.
     * Kaynak: citydb/il-ilce-semt-mahalle dökümü. Tekrar çalıştırılabilir.
     */
    public function run(): void
    {
        $cities = json_decode(file_get_contents(database_path('data/cities.json')), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($cities) {
            City::upsert(
                array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name']], $cities),
                ['id'],
                ['name'],
            );

            $districts = [];
            foreach ($cities as $city) {
                foreach ($city['districts'] as $district) {
                    $districts[] = ['id' => $district['id'], 'city_id' => $city['id'], 'name' => $district['name']];
                }
            }

            foreach (array_chunk($districts, 500) as $chunk) {
                District::upsert($chunk, ['id'], ['city_id', 'name']);
            }
        });
    }
}
