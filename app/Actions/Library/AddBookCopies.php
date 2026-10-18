<?php

namespace App\Actions\Library;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AddBookCopies
{
    /**
     * Register new copies of the book, numbered after the highest code the team's library has ever used.
     *
     * Deleted copies keep their codes, so a number printed on an old label never comes back on another book.
     *
     * @return Collection<int, BookCopy>
     */
    public function handle(Book $book, int $count = 1): Collection
    {
        return DB::transaction(function () use ($book, $count) {
            Team::whereKey($book->team_id)->lockForUpdate()->firstOrFail();

            $lastCode = (int) BookCopy::withTrashed()->where('team_id', $book->team_id)->max('code');

            return $book->copies()->createMany(array_map(fn (int $offset) => [
                'team_id' => $book->team_id,
                'code' => $lastCode + $offset,
            ], range(1, $count)));
        });
    }
}
