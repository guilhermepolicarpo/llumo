<?php

namespace App\Rules;

use App\Enums\Weekday;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class PassTypeRules
{
    /**
     * Get the validation rules used to validate when the center gives a pass type remotely: the weekdays, and the
     * time the assisted person rests, given as both ends or neither.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|object>>
     */
    public static function remoteSchedule(): array
    {
        return [
            'remoteWeekdays' => ['array'],
            'remoteWeekdays.*' => ['distinct', Rule::enum(Weekday::class)],
            'remoteStartsAt' => ['nullable', 'required_with:remoteEndsAt', 'date_format:H:i'],
            'remoteEndsAt' => ['nullable', 'required_with:remoteStartsAt', 'date_format:H:i', 'after:remoteStartsAt'],
        ];
    }
}
