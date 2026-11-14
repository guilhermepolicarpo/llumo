<?php

namespace App\Rules;

use App\Enums\AppointmentMode;
use App\Models\Appointment;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AppointmentRules
{
    /**
     * Get the validation rules used to validate an appointment for the given team, such as the one being edited.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|object>>
     */
    public static function all(Team $team, ?Appointment $appointment = null): array
    {
        return [
            'appointmentTypeId' => static::appointmentTypeId($team),
            'mode' => static::mode(),
            'reason' => static::reason(),
            'assistedPersonId' => static::assistedPersonId($team),
            'scheduledOn' => [...static::scheduledOn(), new UniqueAppointmentOfDay($team, $appointment?->id)],
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
     * Get the validation rules used to validate the reason the assisted person sought care, required for remote appointments
     * and left out of in-person ones.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function reason(): array
    {
        return ['exclude_unless:mode,'.AppointmentMode::Remote->value, 'required', 'string', 'max:2000'];
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
     * Get the validation rules used to validate an appointment's date, which cannot be in the past.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    public static function scheduledOn(): array
    {
        return ['required', Rule::date()->todayOrAfter()];
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
