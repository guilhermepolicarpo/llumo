<?php

namespace App\Actions\Appointments;

use App\Concerns\NormalizesAppointmentAttributes;
use App\Models\Appointment;

class UpdateAppointment
{
    use NormalizesAppointmentAttributes;

    /**
     * Update an existing appointment.
     *
     * @param  array{appointment_type_id: int, assisted_person_id: int, mode: string, scheduled_on: string, notes?: ?string}  $attributes
     */
    public function handle(Appointment $appointment, array $attributes): Appointment
    {
        $appointment->update($this->attributesToPersist($attributes));

        return $appointment;
    }
}
