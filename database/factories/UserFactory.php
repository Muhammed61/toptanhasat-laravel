<?php

namespace Database\Factories;

use App\Enums\CksStatus;
use App\Models\User;
use App\Support\TcKimlik;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '5'.fake()->unique()->numerify('#########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'terms_accepted_at' => now(),
            'remember_token' => Str::random(10),
            'tc_kimlik' => $tc = TcKimlik::random(),
            'tc_hash' => TcKimlik::hash($tc),
            'cks_number' => fake()->numerify('########'),
            'cks_status' => CksStatus::Approved,
            'cks_submitted_at' => now(),
            'cks_reviewed_at' => now(),
        ];
    }

    /** TC'si olmayan (doğrulama gelmeden önce üye olmuş) hesap */
    public function withoutTc(): static
    {
        return $this->state(fn () => ['tc_kimlik' => null, 'tc_hash' => null]);
    }

    /** ÇKS göndermemiş üye (alıcı) */
    public function withoutCks(): static
    {
        return $this->state(fn () => ['cks_number' => null, 'cks_status' => null, 'cks_submitted_at' => null, 'cks_reviewed_at' => null]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
