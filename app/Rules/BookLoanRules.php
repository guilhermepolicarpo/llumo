<?php

namespace App\Rules;

use App\Models\BookCopy;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class BookLoanRules
{
    /**
     * Get the validation rules used to validate a new loan of one of the team's copies.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|object>>
     */
    public static function all(Team $team): array
    {
        return [
            'assistedPersonId' => ['required', 'integer', Rule::exists('assisted_people', 'id')->where('team_id', $team->id)->withoutTrashed()],
            'bookCopyId' => ['required', 'integer', Rule::exists(BookCopy::class, 'id')->where('team_id', $team->id)->withoutTrashed()],
            'loanedOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'dueOn' => ['required', 'date_format:Y-m-d', 'after_or_equal:loanedOn'],
            'loanNotes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the validation rules used to validate the date a copy was returned.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function returnedOn(): array
    {
        return ['required', 'date_format:Y-m-d', 'before_or_equal:today'];
    }
}
