<?php

namespace App\Actions\Appointments;

use App\Concerns\NormalizesAppointmentAttributes;
use App\Models\Appointment;
use App\Models\Team;
use App\Models\User;

class CreateAppointment
{
    use NormalizesAppointmentAttributes;

    /**
     * Schedule a new appointment for the given team, remembering who scheduled it.
     *
     * @param  array{appointment_type_id: int, assisted_person_id: int, mode: string, scheduled_on: string, notes?: ?string}  $attributes
     */
    public function handle(Team $team, User $creator, array $attributes): Appointment
    {
        return $team->appointments()->create([
            ...$this->attributesToPersist($attributes),
            'creator_id' => $creator->id,
        ]);
    }
}
