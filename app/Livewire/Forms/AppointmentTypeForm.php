<?php

namespace App\Livewire\Forms;

use App\Enums\Weekday;
use App\Models\AppointmentType;
use App\Models\Team;
use App\Rules\AppointmentTypeRules;
use Livewire\Attributes\Locked;
use Livewire\Form;

class AppointmentTypeForm extends Form
{
    /**
     * The appointment type being edited, none when creating one.
     */
    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public bool $requiresRecord = false;

    public string $dailyLimit = '';

    /**
     * The weekdays on which appointments of the type take place, none meaning any day.
     *
     * @var list<string>
     */
    public array $weekdays = [];

    /**
     * Fill the form with the given appointment type, to edit it.
     */
    public function setAppointmentType(AppointmentType $appointmentType): void
    {
        $this->editingId = $appointmentType->id;
        $this->name = $appointmentType->name;
        $this->requiresRecord = $appointmentType->requires_record;
        $this->dailyLimit = (string) $appointmentType->daily_limit;
        $this->weekdays = array_values($appointmentType->weekdays?->map(fn (Weekday $weekday): string => (string) $weekday->value)->all() ?? []);
    }

    /**
     * Validate the form for the given team and map it to the attribute shape expected by the create/update actions.
     *
     * @return array{name: string, requires_record: bool, daily_limit: ?int, weekdays: ?list<int>}
     */
    public function validatedAttributes(Team $team, ?AppointmentType $ignore = null): array
    {
        $this->name = trim($this->name);

        $validated = $this->validate([
            'name' => AppointmentTypeRules::name($team, $ignore),
            'requiresRecord' => AppointmentTypeRules::requiresRecord(),
            'dailyLimit' => AppointmentTypeRules::dailyLimit(),
            ...AppointmentTypeRules::weekdays(),
        ]);

        $weekdays = array_map('intval', $validated['weekdays'] ?? []);
        sort($weekdays);

        return [
            'name' => $validated['name'],
            'requires_record' => $validated['requiresRecord'],
            'daily_limit' => (int) $validated['dailyLimit'] ?: null,
            'weekdays' => $weekdays ?: null,
        ];
    }
}
