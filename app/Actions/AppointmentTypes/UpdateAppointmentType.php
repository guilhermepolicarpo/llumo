<?php

namespace App\Actions\AppointmentTypes;

use App\Models\AppointmentType;

class UpdateAppointmentType
{
    /**
     * Update the given appointment type.
     *
     * @param  array{name: string, requires_record?: bool, daily_limit?: ?int, weekdays?: ?list<int>}  $attributes
     */
    public function handle(AppointmentType $appointmentType, array $attributes): AppointmentType
    {
        $appointmentType->update([
            'name' => trim($attributes['name']),
            'requires_record' => $attributes['requires_record'] ?? false,
            'daily_limit' => $attributes['daily_limit'] ?? null,
            'weekdays' => $attributes['weekdays'] ?? null,
        ]);

        return $appointmentType;
    }
}
