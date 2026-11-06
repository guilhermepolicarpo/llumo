<?php

namespace App\Policies;

use App\Enums\AppointmentAction;
use App\Models\Appointment;
use App\Models\Team;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $this->belongsToAppointmentsTeam($user, $appointment) && $appointment->status->isEditable();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }

    /**
     * Determine whether the user can take the model through the given step of its attendance flow.
     */
    public function perform(User $user, Appointment $appointment, AppointmentAction $action): bool
    {
        return $this->belongsToAppointmentsTeam($user, $appointment) && $action->isPermittedFor($appointment, $user);
    }

    /**
     * Determine whether the user can print the model's attendance sheet.
     */
    public function printAttendanceSheet(User $user, Appointment $appointment): bool
    {
        return $this->belongsToAppointmentsTeam($user, $appointment) && $appointment->hasPrintableAttendanceSheet();
    }

    /**
     * Determine whether the user belongs to the appointment's team.
     */
    private function belongsToAppointmentsTeam(User $user, Appointment $appointment): bool
    {
        return $user->belongsToTeam($appointment->team);
    }
}
