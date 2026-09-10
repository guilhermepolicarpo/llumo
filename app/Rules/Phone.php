<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Phone implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || static::digits($value) === null) {
            $fail(__('The :attribute must be a valid Brazilian phone number.'));
        }
    }

    /**
     * Reduce a phone number to its ten or eleven significant digits.
     */
    public static function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return in_array(strlen((string) $digits), [10, 11], true) ? $digits : null;
    }

    /**
     * Format a phone number for display as "(00) 0000-0000" or "(00) 00000-0000".
     */
    public static function format(?string $value): string
    {
        $digits = static::digits($value);

        if ($digits === null) {
            return '';
        }

        $ddd = substr($digits, 0, 2);
        $subscriber = substr($digits, 2);

        return sprintf(
            '(%s) %s-%s',
            $ddd,
            substr($subscriber, 0, -4),
            substr($subscriber, -4),
        );
    }
}
