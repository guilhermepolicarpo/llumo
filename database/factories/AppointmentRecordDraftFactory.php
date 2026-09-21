<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentRecordDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentRecordDraft>
 */
class AppointmentRecordDraftFactory extends Factory
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
            'user_id' => User::factory(),
            'data' => ['observations' => fake()->sentence()],
        ];
    }
}
