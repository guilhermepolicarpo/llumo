<?php

namespace App\Rules;

use App\Enums\BookCopyStatus;
use App\Models\BookCopy;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class BookCopyRules
{
    /**
     * Get the validation rules used to validate a copy of one of the team's books, ignoring the copy being edited.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|object>>
     */
    public static function all(Team $team, ?BookCopy $ignore = null): array
    {
        return [
            'copyCode' => static::code($team, $ignore),
            'copyStatus' => static::status(),
            'copyAcquiredOn' => static::acquiredOn(),
            'copyNotes' => static::notes(),
        ];
    }

    /**
     * Get the validation rules used to validate a copy code, which no other copy of the team may ever have used.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function code(Team $team, ?BookCopy $ignore = null): array
    {
        return [
            'required',
            'integer',
            'min:1',
            'max:99999999',
            Rule::unique(BookCopy::class, 'code')->where('team_id', $team->id)->ignore($ignore),
        ];
    }

    /**
     * Get the validation rules used to validate a copy's status.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function status(): array
    {
        return ['required', Rule::enum(BookCopyStatus::class)];
    }

    /**
     * Get the validation rules used to validate the date a copy was acquired.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function acquiredOn(): array
    {
        return ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'];
    }

    /**
     * Get the validation rules used to validate the notes about a copy.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function notes(): array
    {
        return ['nullable', 'string', 'max:255'];
    }
}
