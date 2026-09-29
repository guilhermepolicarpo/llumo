<?php

namespace Database\Factories;

use App\Enums\Weekday;
use App\Models\AppointmentType;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentType>
 */
class AppointmentTypeFactory extends Factory
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
     * Indicate that appointments of this type are attended by filling a record.
     */
    public function withRecord(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_record' => true,
        ]);
    }

    /**
     * Indicate that at most the given number of appointments of this type fit in a day.
     */
    public function withDailyLimit(int $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'daily_limit' => $limit,
        ]);
    }

    /**
     * Indicate that appointments of this type only take place on the given weekdays.
     */
    public function onWeekdays(Weekday ...$weekdays): static
    {
        return $this->state(fn (array $attributes) => [
            'weekdays' => $weekdays,
        ]);
    }

    /**
     * Indicate that the appointment type has been deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
