<?php

namespace App\Concerns;

use App\Enums\AppointmentMode;
use App\Models\AppointmentType;
use App\Models\AssistedPerson;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

trait InteractsWithAppointmentForm
{
    public string $appointmentTypeId = '';

    public string $mode = AppointmentMode::InPerson->value;

    public ?int $assistedPersonId = null;

    public string $assistedPersonSearch = '';

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
        return Auth::user()->currentTeam->appointmentTypes()->orderBy('name')->get(['id', 'name']);
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
     * Get the assisted person currently selected in the form.
     */
    #[Computed]
    public function selectedAssistedPerson(): ?AssistedPerson
    {
        if ($this->assistedPersonId === null) {
            return null;
        }

        return Auth::user()->currentTeam->assistedPeople()->find($this->assistedPersonId);
    }

    /**
     * Get the assisted people whose name matches the search term, or null when the term is too short to search.
     *
     * @return Collection<int, AssistedPerson>|null
     */
    #[Computed]
    public function assistedPersonSuggestions(): ?Collection
    {
        $search = trim($this->assistedPersonSearch);

        if (mb_strlen($search) < 2) {
            return null;
        }

        return Auth::user()->currentTeam->assistedPeople()
            ->whereLike('name', "%{$search}%")
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    /**
     * Select the given assisted person for the appointment, including one just created from the quick-create modal.
     */
    #[On('assisted-person-created')]
    public function selectAssistedPerson(int $assistedPersonId): void
    {
        $assistedPerson = Auth::user()->currentTeam->assistedPeople()->findOrFail($assistedPersonId);

        $this->assistedPersonId = $assistedPerson->id;
        $this->assistedPersonSearch = '';

        unset($this->selectedAssistedPerson);
        $this->resetValidation('assistedPersonId');
    }

    /**
     * Clear the selected assisted person so another one can be searched.
     */
    public function clearAssistedPerson(): void
    {
        $this->assistedPersonId = null;

        unset($this->selectedAssistedPerson);
    }

    /**
     * Select an appointment type just created from the quick-create modal.
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
