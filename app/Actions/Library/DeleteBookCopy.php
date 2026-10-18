<?php

namespace App\Actions\Library;

use App\Models\BookCopy;
use Illuminate\Validation\ValidationException;

class DeleteBookCopy
{
    /**
     * Delete the copy, keeping its loans and its code reserved, unless it is lent out.
     */
    public function handle(BookCopy $copy): void
    {
        if ($copy->openLoan()->exists()) {
            throw ValidationException::withMessages([
                'copy' => __('This copy is lent out. Register its return before deleting it.'),
            ]);
        }

        $copy->delete();
    }
}
