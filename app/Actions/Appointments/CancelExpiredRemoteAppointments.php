<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentAction;
use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;

class CancelExpiredRemoteAppointments
{
    /**
     * Cancel every remote appointment whose record was never entered once its editing window has closed.
     *
     * Remote appointments are never marked as no-shows, so they stay pending while any member can still enter
     * their record; afterwards only admins could, by reopening them, and leaving them scheduled would only pile them up.
     */
    public function handle(): int
    {
        return Appointment::query()
            ->whereIn('status', AppointmentAction::Cancel->fromStatuses())
            ->whereNotIn('mode', AppointmentMode::expectingArrival())
            ->where('scheduled_on', '<', Appointment::recordEditingCutoff()->toDateString())
            ->update(['status' => AppointmentStatus::Canceled]);
    }
}
