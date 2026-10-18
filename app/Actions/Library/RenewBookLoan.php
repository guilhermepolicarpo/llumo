<?php

namespace App\Actions\Library;

use App\Models\BookLoan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RenewBookLoan
{
    /**
     * Extend the loan by the team's loan period, counted from its due date or from today when it is overdue.
     */
    public function handle(BookLoan $loan): BookLoan
    {
        return DB::transaction(function () use ($loan) {
            $team = $loan->team;
            $loan = BookLoan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($loan->returned_on !== null) {
                throw ValidationException::withMessages([
                    'loan' => __('This book has already been returned.'),
                ]);
            }

            if ($team->loan_max_renewals !== null && $loan->renewals_count >= $team->loan_max_renewals) {
                throw ValidationException::withMessages([
                    'loan' => trans_choice(
                        'This loan has already been renewed :count time, the most the library allows.|This loan has already been renewed :count times, the most the library allows.',
                        $team->loan_max_renewals,
                    ),
                ]);
            }

            $loan->update([
                'due_on' => $loan->due_on->max(today())->addDays($team->loan_period_days)->toDateString(),
                'renewals_count' => $loan->renewals_count + 1,
            ]);

            return $loan;
        });
    }
}
