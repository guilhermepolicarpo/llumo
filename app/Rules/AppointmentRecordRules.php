<?php

namespace App\Rules;

use App\Enums\Catalog;
use App\Enums\InfiltrationRemovalPlace;
use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AppointmentRecordRules
{
    /**
     * Get the validation rules used to validate the record filled for the given appointment.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|object>>
     */
    public static function all(Appointment $appointment): array
    {
        $team = $appointment->team;
        $afterAppointment = 'after:'.$appointment->scheduled_on->toDateString();
        $schedulesRemoval = 'required_if:infiltrationRemovalPlace,'.InfiltrationRemovalPlace::AtTheCenter->value;

        return [
            'mentorId' => ['required', ...CatalogRules::entryId($team, Catalog::Mentor)],
            'fluidicRemedyIds' => ['array'],
            'fluidicRemedyIds.*' => CatalogRules::entryId($team, Catalog::FluidicRemedy),
            'fluidInstructions' => ['nullable', 'string', 'max:500'],
            'guidanceIds' => ['array'],
            'guidanceIds.*' => CatalogRules::entryId($team, Catalog::Guidance),
            'guidanceDetails' => ['array'],
            'guidanceDetails.*' => ['nullable', 'string', 'max:100'],
            'passPrescriptions' => ['array'],
            'passPrescriptions.*.pass_type_id' => ['required', ...CatalogRules::entryId($team, Catalog::PassType)],
            'passPrescriptions.*.quantity' => ['required', 'integer', 'between:1,99'],
            'passPrescriptions.*.mode' => AppointmentRules::mode(),
            'infiltrationSite' => ['nullable', 'string', 'max:150'],
            'infiltrationRemoveOn' => ['nullable', 'required_with:infiltrationSite', $schedulesRemoval, 'date', $afterAppointment],
            'infiltrationRemovalPlace' => ['nullable', 'required_with:infiltrationSite', 'string', Rule::enum(InfiltrationRemovalPlace::class)],
            'removalAppointmentTypeId' => ['nullable', $schedulesRemoval, ...static::appointmentTypeId($appointment)],
            'returnOn' => ['nullable', 'required_if_accepted:schedulesReturn', 'date', $afterAppointment],
            'schedulesReturn' => ['boolean'],
            'returnAppointmentTypeId' => ['nullable', 'required_if_accepted:schedulesReturn', ...static::appointmentTypeId($appointment)],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get the validation rules used to validate the type of a follow-up appointment.
     *
     * @return array<int, ValidationRule|array<mixed>|string|object>
     */
    protected static function appointmentTypeId(Appointment $appointment): array
    {
        return [
            'integer',
            Rule::exists('appointment_types', 'id')->where('team_id', $appointment->team_id)->withoutTrashed(),
        ];
    }
}
