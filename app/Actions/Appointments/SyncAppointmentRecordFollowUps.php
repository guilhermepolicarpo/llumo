<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentRecord;
use App\Models\User;
use Carbon\CarbonInterface;

class SyncAppointmentRecordFollowUps
{
    public function __construct(
        private CreateAppointment $createAppointment,
        private UpdateAppointment $updateAppointment,
    ) {}

    /**
     * Create, update, or remove the infiltration removal and return appointments requested by the record,
     * linking them to the record without saving it. New follow-ups are scheduled in the name of the given user.
     *
     * Without a linked return, only a changed return date schedules one: saving a record again must not bring back
     * a return removed from the schedule, nor schedule a date kept only as an indication.
     *
     * @param  array{removal_appointment_type_id: ?int, return_appointment_type_id: ?int}  $attributes
     */
    public function handle(Appointment $appointment, AppointmentRecord $record, User $user, array $attributes): void
    {
        $record->infiltration_removal_appointment_id = $this->syncFollowUp(
            appointment: $appointment,
            user: $user,
            followUp: $record->infiltrationRemovalAppointment,
            scheduledOn: $record->infiltration_removal_place?->schedulesRemoval() ? $record->infiltration_remove_on : null,
            appointmentTypeId: $attributes['removal_appointment_type_id'],
            mode: AppointmentMode::InPerson,
        );

        $record->return_appointment_id = $this->syncFollowUp(
            appointment: $appointment,
            user: $user,
            followUp: $record->returnAppointment,
            scheduledOn: ($record->returnAppointment !== null || $record->isDirty('return_on')) ? $record->return_on : null,
            appointmentTypeId: $attributes['return_appointment_type_id'],
            mode: $appointment->mode,
        );
    }

    /**
     * Bring a single follow-up appointment in line with the record, returning the id of the linked appointment.
     *
     * A null date means the follow-up is no longer requested. Follow-ups the assisted person already moved
     * through the queue are left untouched. When the person already has an appointment of the type on the
     * date, that one becomes the follow-up instead of a second one being scheduled.
     */
    private function syncFollowUp(
        Appointment $appointment,
        User $user,
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

        $sameDayAppointment = Appointment::query()
            ->where('team_id', $appointment->team_id)
            ->sameDayAs($appointment->assisted_person_id, $appointmentTypeId, $scheduledOn, $followUp?->id)
            ->first(['id']);

        if ($sameDayAppointment !== null) {
            $followUp?->delete();

            return $sameDayAppointment->id;
        }

        $attributes = [
            'appointment_type_id' => $appointmentTypeId,
            'assisted_person_id' => $appointment->assisted_person_id,
            'mode' => $mode->value,
            'scheduled_on' => $scheduledOn->toDateString(),
            'notes' => $followUp?->notes,
        ];

        if ($followUp === null) {
            return $this->createAppointment->handle($appointment->team, $user, $attributes)->id;
        }

        return $this->updateAppointment->handle($followUp, $attributes)->id;
    }
}
