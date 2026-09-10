<?php

namespace Database\Factories;

use App\Enums\BrazilianState;
use App\Models\AssistedPerson;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistedPerson>
 */
class AssistedPersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->name(),
        ];
    }

    /**
     * Indicate that the assisted person has a birth date.
     */
    public function withBirthDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'birth_date' => fake()->dateTimeBetween('-90 years', '-1 years')->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the assisted person has a phone number.
     */
    public function withPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => fake()->numerify('###########'),
        ]);
    }

    /**
     * Indicate that the assisted person has an email address.
     */
    public function withEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    /**
     * Indicate that the assisted person has a full Brazilian address.
     */
    public function withAddress(): static
    {
        return $this->state(fn (array $attributes) => [
            'postal_code' => fake()->numerify('########'),
            'street' => fake()->streetName(),
            'number' => (string) fake()->buildingNumber(),
            'complement' => fake()->optional()->randomElement(['Sala 2', 'Fundos', 'Bloco B']),
            'district' => fake()->citySuffix(),
            'city' => fake()->city(),
            'state' => fake()->randomElement(BrazilianState::cases()),
        ]);
    }
}
