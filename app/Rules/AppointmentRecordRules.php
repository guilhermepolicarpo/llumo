<?php

namespace App\Rules;

use App\Enums\AppointmentMode;
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
        $followUpDate = 'date_format:Y-m-d,Y-m-d H:i';
        $afterAppointment = 'after_or_equal:'.$appointment->scheduled_on->addDay()->toDateString();
        $schedulesRemoval = 'required_if:infiltrationRemovalPlace,'.InfiltrationRemovalPlace::AtTheCenter->value;
        $removedElsewhere = 'exclude_unless:infiltrationRemovalPlace,'.InfiltrationRemovalPlace::AtTheCenter->value;

        return [
            'mentorId' => ['required', ...CatalogRules::entryId($team, Catalog::Mentor)],
            'fluidicRemedyIds' => ['array', 'required_with:fluidInstructions'],
            'fluidicRemedyIds.*' => CatalogRules::entryId($team, Catalog::FluidicRemedy),
            'fluidInstructions' => ['nullable', 'string', 'max:500'],
            'guidanceIds' => ['array'],
            'guidanceIds.*' => CatalogRules::entryId($team, Catalog::Guidance),
            'guidanceDetails' => ['array'],
            'guidanceDetails.*' => ['nullable', 'string', 'max:100'],
            ...static::passPrescriptions($appointment),
            'infiltrationSite' => ['nullable', 'required_with:infiltrationRemoveAt,infiltrationRemovalPlace', 'string', 'max:150'],
            'infiltrationRemoveAt' => ['nullable', 'required_with:infiltrationSite', $schedulesRemoval, $followUpDate, $afterAppointment],
            'infiltrationRemovalPlace' => ['nullable', 'required_with:infiltrationSite', 'string', Rule::enum(InfiltrationRemovalPlace::class)],
            'removalAppointmentTypeId' => [$removedElsewhere, 'required', ...static::appointmentTypeId($appointment)],
            'returnAt' => ['nullable', $followUpDate, $afterAppointment],
            'returnAppointmentTypeId' => ['exclude_without:returnAt', 'required', ...static::appointmentTypeId($appointment)],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get the validation rules used to validate the passes prescribed on the record, with the days of the remote ones.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|object>>
     */
    public static function passPrescriptions(Appointment $appointment): array
    {
        $givenRemotely = 'exclude_unless:passPrescriptions.*.mode,'.AppointmentMode::Remote->value;

        return [
            'passPrescriptions' => ['array'],
            'passPrescriptions.*.pass_type_id' => ['required', ...CatalogRules::entryId($appointment->team, Catalog::PassType), new DistinctPassPrescription],
            'passPrescriptions.*.quantity' => ['required', 'integer', 'between:1,99'],
            'passPrescriptions.*.mode' => AppointmentRules::mode(),
            'passPrescriptions.*.dates' => [$givenRemotely, 'array', new RemotePassDates],
            'passPrescriptions.*.dates.*' => [$givenRemotely, 'nullable', 'date_format:Y-m-d', 'after:'.$appointment->scheduled_on->toDateString()],
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
