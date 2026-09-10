<?php

use App\Models\AssistedPerson;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?int $assistedPersonId = null;

    public string $assistedPersonName = '';

    #[On('confirm-delete-assisted-person')]
    public function confirmDeleteAssistedPerson(int $assistedPersonId, string $assistedPersonName): void
    {
        $this->assistedPersonId = $assistedPersonId;
        $this->assistedPersonName = $assistedPersonName;

        Flux::modal('delete-assisted-person')->show();
    }

    public function deleteAssistedPerson(): void
    {
        $assistedPerson = Auth::user()->currentTeam->assistedPeople()->findOrFail($this->assistedPersonId);

        Gate::authorize('delete', $assistedPerson);

        $assistedPerson->delete();

        Flux::modal('delete-assisted-person')->close();

        $this->dispatch('assisted-person-deleted');

        Flux::toast(variant: 'success', text: __('Assisted person deleted.'));
    }
}; ?>

<flux:modal name="delete-assisted-person" focusable class="max-w-lg">
    <form wire:submit="deleteAssistedPerson" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete assisted person') }}</flux:heading>
            <flux:subheading>
                {{ __('Are you sure you want to delete :name? This action cannot be undone.', ['name' => $assistedPersonName]) }}
            </flux:subheading>
        </div>
        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" type="submit" wire:loading.attr="disabled" data-test="delete-assisted-person-confirm">
                {{ __('Delete assisted person') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
