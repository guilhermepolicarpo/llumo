<?php

namespace Database\Factories;

use App\Models\AssistedPerson;
use App\Models\BookCopy;
use App\Models\BookLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookLoan>
 */
class BookLoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_copy_id' => BookCopy::factory(),
            'team_id' => fn (array $attributes) => BookCopy::query()->whereKey($attributes['book_copy_id'])->value('team_id'),
            'assisted_person_id' => fn (array $attributes) => AssistedPerson::factory()->state(['team_id' => $attributes['team_id']]),
            'loaned_on' => today()->toDateString(),
            'due_on' => today()->addDays(14)->toDateString(),
        ];
    }

    /**
     * Indicate that the loan's due date has passed without the copy being returned.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'loaned_on' => today()->subDays(20)->toDateString(),
            'due_on' => today()->subDays(6)->toDateString(),
        ]);
    }

    /**
     * Indicate that the copy has been returned.
     */
    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'loaned_on' => today()->subDays(20)->toDateString(),
            'due_on' => today()->subDays(6)->toDateString(),
            'returned_on' => today()->subDays(7)->toDateString(),
        ]);
    }
}
