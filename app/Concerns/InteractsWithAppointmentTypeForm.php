<?php

namespace App\Concerns;

use App\Models\AppointmentType;
use App\Models\Team;
use App\Rules\AppointmentTypeRules;

trait InteractsWithAppointmentTypeForm
{
    public string $name = '';

    public bool $requiresRecord = false;

    public string $dailyLimit = '';

    /**
     * Validate the form for the given team and map it to the attribute shape expected by the create/update actions.
     *
     * @return array{name: string, requires_record: bool, daily_limit: ?int}
     */
    protected function validatedAppointmentTypeAttributes(Team $team, ?AppointmentType $ignore = null): array
    {
        $this->name = trim($this->name);

        $validated = $this->validate([
            'name' => AppointmentTypeRules::name($team, $ignore),
            'requiresRecord' => AppointmentTypeRules::requiresRecord(),
            'dailyLimit' => AppointmentTypeRules::dailyLimit(),
        ]);

        return [
            'name' => $validated['name'],
            'requires_record' => $validated['requiresRecord'],
            'daily_limit' => (int) $validated['dailyLimit'] ?: null,
        ];
    }
}
