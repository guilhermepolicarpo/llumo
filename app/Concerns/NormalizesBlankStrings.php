<?php

namespace App\Concerns;

trait NormalizesBlankStrings
{
    /**
     * Normalize a value, treating non-strings and blank strings as absent.
     */
    protected function blankToNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Normalize a numeric value, treating blanks as absent.
     */
    protected function blankToNullInteger(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
