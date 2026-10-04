<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class TakeOverAppointment
{
    /**
     * Hand an appointment being attended over to the user who records it, from the moment they opened its record.
     *
     * Nothing changes when the appointment is no longer in progress or the user already attends it.
     */
    public function handle(Appointment $appointment, User $user, CarbonImmutable $openedAt): void
    {
        $takenOver = Appointment::query()
            ->whereKey($appointment->id)
            ->where('status', AppointmentStatus::InProgress)
            ->where(fn (Builder $query): Builder => $query->whereNull('attendant_id')->orWhere('attendant_id', '!=', $user->id))
            ->update(['attendant_id' => $user->id, 'started_at' => $openedAt]) > 0;

        if ($takenOver) {
            $appointment->refresh();
        }
    }
}
