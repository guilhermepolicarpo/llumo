<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
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
            'title' => fake()->sentence(3),
        ];
    }

    /**
     * Indicate that the book has a valid ISBN-13.
     */
    public function withIsbn(): static
    {
        return $this->state(fn (array $attributes) => [
            'isbn' => fake()->unique()->isbn13(),
        ]);
    }

    /**
     * Indicate that the book has a cover stored on the public disk.
     */
    public function withCover(): static
    {
        return $this->state(fn (array $attributes) => [
            'cover_path' => 'book-covers/'.fake()->uuid().'.jpg',
        ]);
    }

    /**
     * Indicate that the book has been deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
