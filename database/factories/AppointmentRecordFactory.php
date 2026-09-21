<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentRecord>
 */
class AppointmentRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'fluid_instructions' => fake()->optional()->sentence(),
            'observations' => fake()->optional()->sentence(),
        ];
    }
}
