<?php

namespace Database\Factories;

use App\Enums\AppointmentMode;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AssistedPerson;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
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
            'appointment_type_id' => fn (array $attributes) => AppointmentType::factory()->create(['team_id' => $attributes['team_id']]),
            'assisted_person_id' => fn (array $attributes) => AssistedPerson::factory()->create(['team_id' => $attributes['team_id']]),
            'mode' => fake()->randomElement(AppointmentMode::cases()),
            'scheduled_on' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
        ];
    }

    /**
     * Indicate that the appointment is in person.
     */
    public function inPerson(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => AppointmentMode::InPerson,
        ]);
    }

    /**
     * Indicate that the appointment is remote.
     */
    public function remote(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => AppointmentMode::Remote,
        ]);
    }

    /**
     * Indicate that the appointment has notes.
     */
    public function withNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => fake()->sentence(),
        ]);
    }
}
