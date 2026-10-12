<?php

namespace Database\Factories;

use App\Enums\ListingType;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    public function definition(): array
    {
        $category = Category::query()->inRandomOrder()->first()
            ?? Category::create(['slug' => 'sebze', 'name' => 'Sebze', 'icon' => 'sebze']);
        $product = $category->products()->inRandomOrder()->value('name')
            ?? $category->products()->create(['name' => 'Domates'])->name;
        $city = City::query()->inRandomOrder()->first() ?? City::create(['name' => 'Test İli']);
        $district = $city->districts()->inRandomOrder()->first() ?? $city->districts()->create(['name' => 'Merkez']);

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(ListingType::cases()),
            'category' => $category->slug,
            'product' => $product,
            'amount' => fake()->numberBetween(1, 50),
            'unit' => 'ton',
            'price' => fake()->randomFloat(2, 5, 100),
            'price_negotiable' => false,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'show_phone' => true,
        ];
    }
}
