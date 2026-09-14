<?php

namespace App\Rules;

use App\Enums\AppointmentMode;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AppointmentRules
{
    /**
     * Get the validation rules used to validate an appointment for the given team.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|object>>
     */
    public static function all(Team $team): array
    {
        return [
            'appointmentTypeId' => static::appointmentTypeId($team),
            'mode' => static::mode(),
            'assistedPersonId' => static::assistedPersonId($team),
            'scheduledOn' => static::scheduledOn(),
            'notes' => static::notes(),
        ];
    }

    /**
     * Get the validation rules used to validate an appointment's type.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function appointmentTypeId(Team $team): array
    {
        return [
            'required',
            'integer',
            Rule::exists('appointment_types', 'id')->where('team_id', $team->id)->withoutTrashed(),
        ];
    }

    /**
     * Get the validation rules used to validate an appointment's mode.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function mode(): array
    {
        return ['required', 'string', Rule::enum(AppointmentMode::class)];
    }

    /**
     * Get the validation rules used to validate an appointment's assisted person.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function assistedPersonId(Team $team): array
    {
        return [
            'required',
            'integer',
            Rule::exists('assisted_people', 'id')->where('team_id', $team->id)->withoutTrashed(),
        ];
    }

    /**
     * Get the validation rules used to validate an appointment's date.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function scheduledOn(): array
    {
        return ['required', 'date'];
    }

    /**
     * Get the validation rules used to validate an appointment's notes.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function notes(): array
    {
        return ['nullable', 'string', 'max:2000'];
    }
}
