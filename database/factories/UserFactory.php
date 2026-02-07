<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'user',
            'is_active' => true,
            'must_change_password' => false,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => 'admin',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes): array => [
            'must_change_password' => true,
        ]);
    }
}
