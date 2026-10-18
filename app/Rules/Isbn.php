<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Isbn implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || static::toIsbn13($value) === null) {
            $fail(__('The :attribute must be a valid ISBN.'));
        }
    }

    /**
     * Reduce an ISBN to its significant characters: 10 digits, the last one possibly an X, or 13 digits.
     */
    public static function digits(?string $value): ?string
    {
        $digits = strtoupper((string) preg_replace('/[\s.-]/', '', (string) $value));

        return preg_match('/^(\d{9}[\dX]|\d{13})$/', $digits) === 1 ? $digits : null;
    }

    /**
     * Convert a valid ISBN-10 or ISBN-13 into the ISBN-13 it is stored as, or null when its check digit is wrong.
     */
    public static function toIsbn13(?string $value): ?string
    {
        $digits = static::digits($value);

        if ($digits === null) {
            return null;
        }

        if (strlen($digits) === 13) {
            return static::isbn13CheckDigit(substr($digits, 0, 12)) === $digits[12] ? $digits : null;
        }

        if (static::isbn10CheckDigit(substr($digits, 0, 9)) !== $digits[9]) {
            return null;
        }

        $prefixed = '978'.substr($digits, 0, 9);

        return $prefixed.static::isbn13CheckDigit($prefixed);
    }

    /**
     * Compute the check digit of the first twelve digits of an ISBN-13.
     */
    private static function isbn13CheckDigit(string $twelveDigits): string
    {
        $sum = 0;

        foreach (str_split($twelveDigits) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - $sum % 10) % 10);
    }

    /**
     * Compute the check character of the first nine digits of an ISBN-10, which is X when it stands for ten.
     */
    private static function isbn10CheckDigit(string $nineDigits): string
    {
        $sum = 0;

        foreach (str_split($nineDigits) as $index => $digit) {
            $sum += (int) $digit * (10 - $index);
        }

        $check = (11 - $sum % 11) % 11;

        return $check === 10 ? 'X' : (string) $check;
    }
}
