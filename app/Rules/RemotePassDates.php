<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Translation\PotentiallyTranslatedString;

class RemotePassDates implements DataAwareRule, ValidationRule
{
    /**
     * The data under validation, which holds the quantity prescribed on the same row.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Fail when a remote pass prescription is given more dates than passes, or the same date twice.
     *
     * Blank dates are left to be written by hand on the printed sheet, so they are not counted. An invalid quantity is
     * left to the rules of its own field.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $dates = array_filter($value, fn (mixed $date): bool => is_string($date) && $date !== '');
        $quantity = Arr::get($this->data, preg_replace('/\.dates$/', '.quantity', $attribute));

        if (is_numeric($quantity) && count($dates) > (int) $quantity) {
            $fail(__('Choose at most :count dates, one for each pass.', ['count' => (int) $quantity]));

            return;
        }

        if (count($dates) !== count(array_unique($dates))) {
            $fail(__('Each pass must be on a different date.'));
        }
    }
}
