<?php

namespace App\Concerns;

trait NormalizesAppointmentAttributes
{
    use NormalizesBlankStrings;

    /**
     * Build the appointment attribute list ready for persistence.
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
            'reason' => $this->blankToNull($attributes['reason'] ?? null),
            'scheduled_on' => $attributes['scheduled_on'],
            'notes' => $this->blankToNull($attributes['notes'] ?? null),
        ];
    }
}
