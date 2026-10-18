<?php

namespace App\Concerns;

use App\Enums\AppointmentMode;
use App\Enums\Weekday;
use App\Models\AppointmentType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

trait InteractsWithAppointmentForm
{
    use InteractsWithAssistedPersonPicker;

    public string $appointmentTypeId = '';

    public string $mode = AppointmentMode::InPerson->value;

    public string $scheduledOn = '';

    public string $notes = '';

    /**
     * Get the current team's appointment types for the type select.
     *
     * @return Collection<int, AppointmentType>
     */
    #[Computed]
    public function appointmentTypes(): Collection
    {
        return Auth::user()->currentTeam->appointmentTypes()->orderBy('name')->get(['id', 'name', 'weekdays']);
    }

    /**
     * Get the types that only take place on some weekdays, keyed by id, for the form to warn about the date right away.
     *
     * @return array<string, array{weekdays: list<int>, hint: string, warning: string}>
     */
    #[Computed]
    public function weekdayRestrictedAppointmentTypes(): array
    {
        return $this->appointmentTypes
            ->filter(fn (AppointmentType $appointmentType): bool => $appointmentType->hasWeekdays())
            ->mapWithKeys(fn (AppointmentType $appointmentType): array => [(string) $appointmentType->id => [
                'weekdays' => $appointmentType->weekdays->map(fn (Weekday $weekday): int => $weekday->value)->values()->all(),
                'hint' => __('Only on :weekdays.', ['weekdays' => $appointmentType->weekdaysLabel()]),
                'warning' => $appointmentType->notOfferedWarning(),
            ]])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function modes(): array
    {
        return AppointmentMode::options();
    }

    /**
     * Select an appointment type just created from the create modal.
     */
    #[On('appointment-type-created')]
    public function appointmentTypeCreated(int $appointmentTypeId): void
    {
        unset($this->appointmentTypes);

        $this->appointmentTypeId = (string) $appointmentTypeId;
        $this->resetValidation('appointmentTypeId');
    }

    /**
     * Map validated form data to the attribute shape expected by the create/update actions.
     *
     * @param  array<string, mixed>  $validated
     * @return array{appointment_type_id: int, assisted_person_id: int, mode: string, scheduled_on: string, notes: ?string}
     */
    protected function appointmentAttributes(array $validated): array
    {
        return [
            'appointment_type_id' => (int) $validated['appointmentTypeId'],
            'assisted_person_id' => (int) $validated['assistedPersonId'],
            'mode' => $validated['mode'],
            'scheduled_on' => $validated['scheduledOn'],
            'notes' => $validated['notes'],
        ];
    }
}
