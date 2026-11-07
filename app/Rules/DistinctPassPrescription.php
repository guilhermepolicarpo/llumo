<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Translation\PotentiallyTranslatedString;

class DistinctPassPrescription implements DataAwareRule, ValidationRule
{
    /**
     * The data under validation, which holds every pass prescription row.
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
     * Fail when an earlier row already prescribes the same pass type in the same mode, so each pass is written once
     * with its whole quantity. The same pass type may still be given in person and remotely.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^passPrescriptions\.(\d+)\.pass_type_id$/', $attribute, $matches) || blank($value)) {
            return;
        }

        $index = (int) $matches[1];
        $rows = Arr::get($this->data, 'passPrescriptions', []);
        $mode = $rows[$index]['mode'] ?? null;

        foreach (array_slice(is_array($rows) ? $rows : [], 0, $index) as $row) {
            if (is_array($row) && (string) ($row['pass_type_id'] ?? '') === (string) $value && ($row['mode'] ?? null) === $mode) {
                $fail(__('This pass type was already added in this mode. Change the quantity of the first one instead.'));

                return;
            }
        }
    }
}
