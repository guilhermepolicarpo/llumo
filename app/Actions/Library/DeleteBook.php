<?php

namespace App\Actions\Library;

use App\Models\Book;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteBook
{
    /**
     * Delete the book and its copies, keeping their loan history, unless one of its copies is lent out.
     */
    public function handle(Book $book): void
    {
        if ($book->loans()->open()->exists()) {
            throw ValidationException::withMessages([
                'book' => __('A copy of this book is lent out. Register its return before deleting the book.'),
            ]);
        }

        DB::transaction(function () use ($book) {
            $book->copies()->delete();
            $book->delete();
        });
    }
}
