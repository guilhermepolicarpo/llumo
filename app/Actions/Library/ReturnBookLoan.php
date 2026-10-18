<?php

namespace App\Actions\Library;

use App\Models\BookLoan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnBookLoan
{
    /**
     * Register that the lent copy came back, received by the given user.
     */
    public function handle(BookLoan $loan, User $receivedBy, string $returnedOn): BookLoan
    {
        return DB::transaction(function () use ($loan, $receivedBy, $returnedOn) {
            $loan = BookLoan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($loan->returned_on !== null) {
                throw ValidationException::withMessages([
                    'loan' => __('This book has already been returned.'),
                ]);
            }

            if ($returnedOn < $loan->loaned_on->toDateString()) {
                throw ValidationException::withMessages([
                    'returnedOn' => __('The return date cannot be before the loan date.'),
                ]);
            }

            $loan->update([
                'returned_on' => $returnedOn,
                'returned_to' => $receivedBy->id,
            ]);

            return $loan;
        });
    }
}
