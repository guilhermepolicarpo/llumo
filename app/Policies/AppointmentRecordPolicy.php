<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentRecordPolicy
{
    /**
     * Determine whether the user can open the record of the given appointment.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        return $user->belongsToTeam($appointment->team)
            && $appointment->usesRecord()
            && ($appointment->status->isAttendable() || $appointment->status->allowsRecordEditing());
    }

    /**
     * Determine whether the user can fill in or correct the record of the given appointment.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $this->view($user, $appointment)
            && $appointment->status->allowsRecordEditing();
    }
}
