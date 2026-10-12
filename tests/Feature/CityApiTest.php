<?php

namespace Tests\Feature;

use Database\Seeders\CitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CitySeeder::class);
    }

    public function test_lists_all_81_cities(): void
    {
        $this->getJson('/api/cities')
            ->assertOk()
            ->assertJsonCount(81)
            ->assertJsonFragment(['id' => 12, 'name' => 'Balıkesir']);
    }

    public function test_lists_districts_of_a_city(): void
    {
        $this->getJson('/api/cities/12/districts')
            ->assertOk()
            ->assertJsonCount(20)
            ->assertJsonFragment(['name' => 'Bandırma']);
    }

    public function test_unknown_city_returns_404(): void
    {
        $this->getJson('/api/cities/999/districts')->assertNotFound();
    }

    public function test_seeder_can_run_twice(): void
    {
        $this->seed(CitySeeder::class);

        $this->getJson('/api/cities')->assertJsonCount(81);
    }
}
