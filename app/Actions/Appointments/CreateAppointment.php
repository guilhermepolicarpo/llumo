<?php

namespace App\Actions\Appointments;

use App\Concerns\NormalizesAppointmentAttributes;
use App\Models\Appointment;
use App\Models\Team;

class CreateAppointment
{
    use NormalizesAppointmentAttributes;

    /**
     * Schedule a new appointment for the given team.
     *
     * @param  array{appointment_type_id: int, assisted_person_id: int, mode: string, scheduled_on: string, notes?: ?string}  $attributes
     */
    public function handle(Team $team, array $attributes): Appointment
    {
        return $team->appointments()->create($this->attributesToPersist($attributes));
    }
}
