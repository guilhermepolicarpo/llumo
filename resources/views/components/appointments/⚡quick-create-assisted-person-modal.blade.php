<?php

use App\Actions\AssistedPeople\CreateAssistedPerson;
use App\Models\AssistedPerson;
use App\Rules\AssistedPersonRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public string $name = '';

    public string $phone = '';

    public string $birthDate = '';

    #[On('open-quick-create-assisted-person')]
    public function openQuickCreateAssistedPerson(string $name = ''): void
    {
        $this->resetValidation();
        $this->reset('phone', 'birthDate');
        $this->name = trim($name);

        Flux::modal('quick-create-assisted-person')->show();
    }

    public function createAssistedPerson(CreateAssistedPerson $createAssistedPerson): void
    {
        $team = Auth::user()->currentTeam;

        Gate::authorize('create', [AssistedPerson::class, $team]);

        $validated = $this->validate([
            'name' => AssistedPersonRules::name(),
            'phone' => AssistedPersonRules::phone(),
            'birthDate' => AssistedPersonRules::birthDate(),
        ]);

        $assistedPerson = $createAssistedPerson->handle($team, [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'birth_date' => $validated['birthDate'],
        ]);

        $this->reset('name', 'phone', 'birthDate');

        Flux::modal('quick-create-assisted-person')->close();

        $this->dispatch('assisted-person-created', assistedPersonId: $assistedPerson->id);

        Flux::toast(variant: 'success', text: __('Assisted person created.'));
    }
}; ?>

<flux:modal name="quick-create-assisted-person" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form wire:submit="createAssistedPerson" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New assisted person') }}</flux:heading>
            <flux:subheading>{{ __('Quick registration. You can complete the address later on the assisted person page.') }}</flux:subheading>
        </div>

        <div class="space-y-4">
            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('John Doe')" required autofocus data-test="quick-assisted-person-name-input" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="phone" :label="__('Phone')" placeholder="(00) 00000-0000" inputmode="numeric" mask="(99) 99999-9999" data-test="quick-assisted-person-phone-input" />
                <flux:input type="date" wire:model="birthDate" :label="__('Birth date')" max="{{ today()->toDateString() }}" data-test="quick-assisted-person-birth-date-input" />
            </div>
        </div>

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" type="submit" wire:loading.attr="disabled" data-test="quick-assisted-person-save-button">{{ __('Register assisted person') }}</flux:button>
        </div>
    </form>
</flux:modal>
