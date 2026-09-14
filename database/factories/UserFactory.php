<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Models\User;

/** @extends Factory<User> */
final class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= 'Correct-Horse-Battery-9',
            'remember_token' => Str::random(10),
            'locale' => 'cs',
            'timezone' => 'Europe/Prague',
            'is_staff' => false,
            'state' => 'active',
        ];
    }

    public function staff(): static
    {
        return $this->state(fn () => ['is_staff' => true]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
