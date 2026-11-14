<?php

namespace App\Concerns;

use App\Enums\AppointmentMode;

trait NormalizesAppointmentAttributes
{
    use NormalizesBlankStrings;

    /**
     * Build the appointment attribute list ready for persistence.
     *
     * The reason for seeking care is only kept for remote appointments.
     *
     * @param  array{appointment_type_id: int, assisted_person_id: int, mode: string, reason?: ?string, scheduled_on: string, notes?: ?string}  $attributes
     * @return array<string, mixed>
     */
    protected function attributesToPersist(array $attributes): array
    {
        return [
            'appointment_type_id' => $attributes['appointment_type_id'],
            'assisted_person_id' => $attributes['assisted_person_id'],
            'mode' => $attributes['mode'],
            'reason' => $attributes['mode'] === AppointmentMode::Remote->value ? $this->blankToNull($attributes['reason'] ?? null) : null,
            'scheduled_on' => $attributes['scheduled_on'],
            'notes' => $this->blankToNull($attributes['notes'] ?? null),
        ];
    }
}
