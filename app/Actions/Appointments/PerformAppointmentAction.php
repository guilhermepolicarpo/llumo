<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentAction;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PerformAppointmentAction
{
    /**
     * Move the appointment to the next status of its attendance flow.
     *
     * Returns false when the action no longer applies, e.g. because someone else already moved the appointment.
     */
    public function handle(Appointment $appointment, AppointmentAction $action, User $user): bool
    {
        return DB::transaction(function () use ($appointment, $action, $user): bool {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if (! $action->isAvailableFor($appointment)) {
                return false;
            }

            return $appointment->update($action->attributes($user));
        });
    }
}
