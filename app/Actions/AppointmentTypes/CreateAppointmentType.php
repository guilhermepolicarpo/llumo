<?php

namespace App\Actions\AppointmentTypes;

use App\Models\AppointmentType;
use App\Models\Team;

class CreateAppointmentType
{
    /**
     * Create a new appointment type for the given team.
     *
     * @param  array{name: string, requires_record?: bool, daily_limit?: ?int}  $attributes
     */
    public function handle(Team $team, array $attributes): AppointmentType
    {
        return $team->appointmentTypes()->create([
            'name' => trim($attributes['name']),
            'requires_record' => $attributes['requires_record'] ?? false,
            'daily_limit' => $attributes['daily_limit'] ?? null,
        ]);
    }
}
