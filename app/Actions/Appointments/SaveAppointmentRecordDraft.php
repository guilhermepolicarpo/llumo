<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentRecordDraft;
use App\Models\User;

class SaveAppointmentRecordDraft
{
    /**
     * Keep the unsaved state of the appointment's record form, without validating it or scheduling anything.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Appointment $appointment, User $user, array $data): AppointmentRecordDraft
    {
        return $appointment->recordDraft()->updateOrCreate([], [
            'user_id' => $user->id,
            'data' => $data,
        ]);
    }
}
