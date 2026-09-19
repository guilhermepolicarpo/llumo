<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentAction;
use App\Models\Appointment;

class MarkMissedAppointmentsAsNoShow
{
    /**
     * Mark every appointment from previous days that was never received as a no-show.
     */
    public function handle(): int
    {
        return Appointment::query()
            ->whereIn('status', AppointmentAction::MarkAsNoShow->fromStatuses())
            ->where('scheduled_on', '<', today()->toDateString())
            ->update(['status' => AppointmentAction::MarkAsNoShow->toStatus()]);
    }
}
