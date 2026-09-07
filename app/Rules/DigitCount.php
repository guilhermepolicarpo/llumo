<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class DigitCount implements ValidationRule
{
    /**
     * @param  array<int, int>  $allowed  The digit counts the value may hold.
     */
    public function __construct(protected array $allowed, protected string $message)
    {
        //
    }

    /**
     * Run the validation rule.
     *
     * Values arrive masked from the form, so only the digits are counted.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $digits = (string) preg_replace('/\D/', '', (string) $value);

        if (! in_array(strlen($digits), $this->allowed, true)) {
            $fail($this->message);
        }
    }
}
