<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use Carbon\CarbonInterface;

class SyncAppointmentRecordFollowUps
{
    public function __construct(
        private CreateAppointment $createAppointment,
        private UpdateAppointment $updateAppointment,
    ) {}

    /**
     * Create, update, or remove the infiltration removal and return appointments requested by the record,
     * linking them to the record without saving it.
     *
     * @param  array{removal_appointment_type_id: ?int, schedules_return: bool, return_appointment_type_id: ?int}  $attributes
     */
    public function handle(Appointment $appointment, AppointmentRecord $record, array $attributes): void
    {
        $record->infiltration_removal_appointment_id = $this->syncFollowUp(
            appointment: $appointment,
            followUp: $record->infiltrationRemovalAppointment,
            scheduledOn: $record->infiltration_removal_place?->schedulesRemoval() ? $record->infiltration_remove_on : null,
            appointmentTypeId: $attributes['removal_appointment_type_id'],
            mode: AppointmentMode::InPerson,
        );

        $record->return_appointment_id = $this->syncFollowUp(
            appointment: $appointment,
            followUp: $record->returnAppointment,
            scheduledOn: $attributes['schedules_return'] ? $record->return_on : null,
            appointmentTypeId: $attributes['return_appointment_type_id'],
            mode: $appointment->mode,
        );
    }

    /**
     * Bring a single follow-up appointment in line with the record, returning the id of the linked appointment.
     *
     * A null date means the follow-up is no longer requested. Follow-ups the assisted person already moved
     * through the queue are left untouched.
     */
    private function syncFollowUp(
        Appointment $appointment,
        ?Appointment $followUp,
        ?CarbonInterface $scheduledOn,
        ?int $appointmentTypeId,
        AppointmentMode $mode,
    ): ?int {
        $isRequested = $scheduledOn !== null && $appointmentTypeId !== null;

        if ($followUp !== null && $followUp->status !== AppointmentStatus::Scheduled) {
            return $followUp->id;
        }

        if (! $isRequested) {
            $followUp?->delete();

            return null;
        }

        $attributes = [
            'appointment_type_id' => $appointmentTypeId,
            'assisted_person_id' => $appointment->assisted_person_id,
            'mode' => $mode->value,
            'scheduled_on' => $scheduledOn->toDateString(),
            'notes' => $followUp?->notes,
        ];

        if ($followUp === null) {
            return $this->createAppointment->handle($appointment->team, $attributes)->id;
        }

        return $this->updateAppointment->handle($followUp, $attributes)->id;
    }
}
