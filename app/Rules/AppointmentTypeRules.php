<?php

namespace App\Rules;

use App\Models\AppointmentType;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AppointmentTypeRules
{
    /**
     * Get the validation rules used to validate an appointment type's name for the given team, ignoring the type being edited.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function name(Team $team, ?AppointmentType $ignore = null): array
    {
        return [
            'required',
            'string',
            'max:100',
            Rule::unique('appointment_types', 'name')->where('team_id', $team->id)->withoutTrashed()->ignore($ignore),
        ];
    }

    /**
     * Get the validation rules used to validate whether an appointment type is attended by filling a record.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function requiresRecord(): array
    {
        return ['boolean'];
    }

    /**
     * Get the validation rules used to validate how many appointments of a type fit in a day.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function dailyLimit(): array
    {
        return ['nullable', 'integer', 'between:1,999'];
    }
}
