<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentAction;
use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;

class MarkMissedAppointmentsAsNoShow
{
    /**
     * Mark every appointment from previous days whose assisted person was expected but never received as a no-show.
     *
     * Remote appointments are left scheduled: the assisted person is never expected at the centre, so their
     * record is still pending rather than missed.
     */
    public function handle(): int
    {
        return Appointment::query()
            ->whereIn('status', AppointmentAction::MarkAsNoShow->fromStatuses())
            ->whereIn('mode', AppointmentMode::expectingArrival())
            ->where('scheduled_on', '<', today()->toDateString())
            ->update(['status' => AppointmentStatus::NoShow]);
    }
}
