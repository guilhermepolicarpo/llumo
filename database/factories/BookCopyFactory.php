<?php

namespace Database\Factories;

use App\Enums\BookCopyStatus;
use App\Models\Book;
use App\Models\BookCopy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookCopy>
 */
class BookCopyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'team_id' => fn (array $attributes) => Book::query()->whereKey($attributes['book_id'])->value('team_id'),
            'code' => fn (array $attributes) => (int) BookCopy::withTrashed()->where('team_id', $attributes['team_id'])->max('code') + 1,
            'status' => BookCopyStatus::Active,
        ];
    }

    /**
     * Indicate that the copy was lost.
     */
    public function lost(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookCopyStatus::Lost,
        ]);
    }

    /**
     * Indicate that the copy was withdrawn from the collection.
     */
    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookCopyStatus::Withdrawn,
        ]);
    }

    /**
     * Indicate that the copy has been deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
