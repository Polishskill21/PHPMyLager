<?php

namespace Database\Factories;

use App\Models\Auth\User;
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
            User::COL_NAME => fake()->name(),
            User::COL_EMAIL => fake()->unique()->safeEmail(),
            User::COL_EMAIL_VERIFIED_AT => now(),
            User::COL_PASSWORD => static::$password ??= Hash::make(env('SEEDER_PASSWORD', 'password')),
            User::COL_REMEMBER_TOKEN => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            User::COL_EMAIL_VERIFIED_AT => null,
        ]);
    }
}
