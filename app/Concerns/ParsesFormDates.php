<?php

namespace App\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

trait ParsesFormDates
{
    /**
     * Read a date field's 'Y-m-d' value, which is empty or invalid while the form is still being filled.
     */
    private function dateFrom(string $value): ?CarbonInterface
    {
        return $value === '' ? null : rescue(fn () => Date::createFromFormat('!Y-m-d', $value), report: false);
    }
}
