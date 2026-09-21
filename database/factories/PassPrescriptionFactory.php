<?php

namespace Database\Factories;

use App\Enums\AppointmentMode;
use App\Models\AppointmentRecord;
use App\Models\PassPrescription;
use App\Models\PassType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PassPrescription>
 */
class PassPrescriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_record_id' => AppointmentRecord::factory(),
            'pass_type_id' => PassType::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'mode' => fake()->randomElement(AppointmentMode::cases()),
        ];
    }
}
