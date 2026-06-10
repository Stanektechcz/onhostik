<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Customer\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'user_id'            => User::factory(),
            'type'               => 'individual',
            'email'              => fake()->unique()->safeEmail(),
            'phone'              => fake()->phoneNumber(),
            'preferred_currency' => 'CZK',
            'preferred_locale'   => 'cs',
            'country_code'       => 'CZ',
        ];
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type'                => 'company',
            'company_name'        => fake()->company(),
            'registration_number' => (string) fake()->numberBetween(10000000, 99999999),
            'vat_number'          => 'CZ' . fake()->numberBetween(10000000, 99999999),
        ]);
    }

    public function vatValidated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'vat_validated_at' => now(),
        ]);
    }

    public function fromCountry(string $countryCode): static
    {
        return $this->state(fn (array $attributes): array => [
            'country_code' => $countryCode,
        ]);
    }
}
