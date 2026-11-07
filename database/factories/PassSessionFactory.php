<?php

namespace Database\Factories;

use App\Models\PassPrescription;
use App\Models\PassSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PassSession>
 */
class PassSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pass_prescription_id' => PassPrescription::factory(),
            'scheduled_on' => fake()->dateTimeBetween('+1 day', '+2 months')->format('Y-m-d'),
        ];
    }
}
