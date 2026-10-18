<?php

namespace App\Concerns;

use App\Models\AssistedPerson;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * The state behind the assisted-person-combobox component: search the current team's assisted people by
 * name and select one of them.
 */
trait InteractsWithAssistedPersonPicker
{
    public ?int $assistedPersonId = null;

    public string $assistedPersonSearch = '';

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
     * Select the given assisted person in the form, including one just created from the create modal.
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
}
