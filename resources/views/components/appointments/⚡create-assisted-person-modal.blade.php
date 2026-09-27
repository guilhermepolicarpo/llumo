<?php

use App\Actions\AssistedPeople\CreateAssistedPerson;
use App\Concerns\InteractsWithAddressForm;
use App\Concerns\InteractsWithAssistedPersonForm;
use App\Models\AssistedPerson;
use App\Rules\AssistedPersonRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use InteractsWithAddressForm, InteractsWithAssistedPersonForm;

    #[On('open-create-assisted-person')]
    public function openCreateAssistedPerson(string $name = ''): void
    {
        $this->resetValidation();
        $this->reset();
        $this->name = trim($name);

        Flux::modal('create-assisted-person')->show();
    }

    public function createAssistedPerson(CreateAssistedPerson $createAssistedPerson): void
    {
        $team = Auth::user()->currentTeam;

        Gate::authorize('create', [AssistedPerson::class, $team]);

        $validated = $this->validate(AssistedPersonRules::all());

        $assistedPerson = $createAssistedPerson->handle($team, $this->assistedPersonAttributes($validated));

        Flux::modal('create-assisted-person')->close();

        $this->dispatch('assisted-person-created', assistedPersonId: $assistedPerson->id);

        Flux::toast(variant: 'success', text: __('Assisted person created.'));
    }
}; ?>

<flux:modal name="create-assisted-person" flyout variant="floating" :show="$errors->isNotEmpty()" class="flex flex-col md:w-xl">
    <form wire:submit="createAssistedPerson" class="flex min-h-0 flex-1 flex-col">
        <x-flyout-layout>
            <div class="pe-8">
                <flux:heading size="lg">{{ __('New assisted person') }}</flux:heading>
                <flux:subheading>{{ __('Register a new assisted person for this Spiritist Center') }}</flux:subheading>
            </div>

            <x-assisted-people.fields />

            <x-pages::address-form :states="$this->states" test-prefix="assisted-person" />

            <x-slot:footer>
                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" data-test="assisted-person-save-button">{{ __('Register assisted person') }}</flux:button>
                </div>
            </x-slot:footer>
        </x-flyout-layout>
    </form>
</flux:modal>
