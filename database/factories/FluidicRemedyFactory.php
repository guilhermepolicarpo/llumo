<?php

namespace Database\Factories;

use App\Models\FluidicRemedy;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FluidicRemedy>
 */
class FluidicRemedyFactory extends Factory
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
            'name' => ucfirst(fake()->unique()->word()).' '.fake()->word(),
        ];
    }

    /**
     * Indicate that the fluidic remedy has been deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
