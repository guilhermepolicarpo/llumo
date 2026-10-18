<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

class LibrarySettingsRules
{
    /**
     * Get the validation rules used to validate a team's loan rules.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public static function all(): array
    {
        return [
            'loanPeriodDays' => ['required', 'integer', 'min:1', 'max:365'],
            'loanMaxRenewals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'loanMaxActivePerPerson' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }
}
