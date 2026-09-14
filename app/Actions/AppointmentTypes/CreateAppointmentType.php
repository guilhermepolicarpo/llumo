<?php

namespace App\Actions\AppointmentTypes;

use App\Models\AppointmentType;
use App\Models\Team;

class CreateAppointmentType
{
    /**
     * Create a new appointment type for the given team.
     *
     * @param  array{name: string}  $attributes
     */
    public function handle(Team $team, array $attributes): AppointmentType
    {
        return $team->appointmentTypes()->create([
            'name' => trim($attributes['name']),
        ]);
    }
}
