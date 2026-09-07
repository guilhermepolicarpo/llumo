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
            'birth_date' => fake()->dateTimeBetween('-90 years', '-1 year'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('119########'),
            'postal_code' => fake()->numerify('########'),
            'street' => fake()->streetName(),
            'number' => (string) fake()->numberBetween(1, 9999),
            'complement' => fake()->optional()->numerify('Apto ##'),
            'district' => fake()->city(),
            'city' => fake()->city(),
            'state' => fake()->randomElement(BrazilianState::cases()),
            'city_ibge_code' => fake()->numerify('#######'),
        ];
    }
}
