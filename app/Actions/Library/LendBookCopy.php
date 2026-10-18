<?php

namespace App\Actions\Library;

use App\Concerns\NormalizesBlankStrings;
use App\Models\AssistedPerson;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LendBookCopy
{
    use NormalizesBlankStrings;

    /**
     * Lend the copy to the assisted person, following the team's loan rules.
     *
     * The copy must be in the collection and not lent out, and the assisted person may neither have an
     * overdue book nor already hold as many books as the team allows.
     *
     * @param  array{loaned_on: string, due_on: string, notes?: ?string}  $attributes
     */
    public function handle(BookCopy $copy, AssistedPerson $assistedPerson, User $registeredBy, array $attributes): BookLoan
    {
        return DB::transaction(function () use ($copy, $assistedPerson, $registeredBy, $attributes) {
            $team = $copy->team;
            $copy = BookCopy::whereKey($copy->id)->lockForUpdate()->firstOrFail();
            AssistedPerson::whereKey($assistedPerson->id)->lockForUpdate()->firstOrFail();

            if (! $copy->isAvailable()) {
                throw ValidationException::withMessages([
                    'bookCopyId' => __('This copy is not available to be lent.'),
                ]);
            }

            $openLoans = $assistedPerson->bookLoans()->open()
                ->selectRaw('count(*) as open_count, sum(case when due_on < ? then 1 else 0 end) as overdue_count', [today()->toDateString()])
                ->toBase()
                ->first();

            if ((int) $openLoans->overdue_count > 0) {
                throw ValidationException::withMessages([
                    'assistedPersonId' => __('This assisted person has an overdue book. Register its return or renew it before lending another one.'),
                ]);
            }

            if ($team->loan_max_active_per_person !== null && (int) $openLoans->open_count >= $team->loan_max_active_per_person) {
                throw ValidationException::withMessages([
                    'assistedPersonId' => trans_choice(
                        'This assisted person already has :count book, the most the library allows at once.|This assisted person already has :count books, the most the library allows at once.',
                        $team->loan_max_active_per_person,
                    ),
                ]);
            }

            return $team->bookLoans()->create([
                'book_copy_id' => $copy->id,
                'assisted_person_id' => $assistedPerson->id,
                'loaned_on' => $attributes['loaned_on'],
                'due_on' => $attributes['due_on'],
                'registered_by' => $registeredBy->id,
                'notes' => $this->blankToNull($attributes['notes'] ?? null),
            ]);
        });
    }
}
