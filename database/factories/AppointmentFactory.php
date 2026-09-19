<?php

namespace Database\Factories;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AssistedPerson;
use App\Models\Team;
use App\Models\User;
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

    /**
     * Indicate that the assisted person was received and is waiting to be attended.
     */
    public function waiting(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Waiting,
            'scheduled_on' => today()->toDateString(),
            'received_at' => now()->subMinutes(30),
        ]);
    }

    /**
     * Indicate that the assisted person is being attended.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::InProgress,
            'scheduled_on' => today()->toDateString(),
            'received_at' => now()->subMinutes(30),
            'started_at' => now()->subMinutes(10),
            'attendant_id' => User::factory(),
        ]);
    }

    /**
     * Indicate that the appointment was completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Completed,
            'scheduled_on' => today()->toDateString(),
            'received_at' => now()->subMinutes(60),
            'started_at' => now()->subMinutes(40),
            'finished_at' => now()->subMinutes(20),
            'attendant_id' => User::factory(),
        ]);
    }

    /**
     * Indicate that the assisted person did not show up.
     */
    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::NoShow,
        ]);
    }

    /**
     * Indicate that the appointment was canceled.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Canceled,
        ]);
    }
}
