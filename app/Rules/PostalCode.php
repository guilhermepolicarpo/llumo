<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PostalCode implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^\d{5}-?\d{3}$/', trim($value)) !== 1) {
            $fail(__('The :attribute must be a valid Brazilian postal code.'));
        }
    }

    /**
     * Reduce a postal code to its eight significant digits.
     */
    public static function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return strlen((string) $digits) === 8 ? $digits : null;
    }

    /**
     * Format a postal code for display as "00000-000".
     */
    public static function format(?string $value): string
    {
        $digits = static::digits($value);

        return $digits === null ? '' : substr($digits, 0, 5).'-'.substr($digits, 5);
    }
}
