<?php

use App\Actions\AssistedPeople\UpdateAssistedPerson;
use App\Concerns\InteractsWithAddressForm;
use App\Concerns\InteractsWithAssistedPersonForm;
use App\Models\AssistedPerson;
use App\Rules\AssistedPersonRules;
use App\Rules\Phone;
use App\Rules\PostalCode;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    use InteractsWithAddressForm, InteractsWithAssistedPersonForm;

    public AssistedPerson $assistedPerson;

    public function mount(AssistedPerson $assistedPerson): void
    {
        Gate::authorize('update', $assistedPerson);

        $this->assistedPerson = $assistedPerson;

        $this->name = $assistedPerson->name;
        $this->birthDate = $assistedPerson->birth_date?->format('Y-m-d') ?? '';
        $this->phone = Phone::format($assistedPerson->phone);
        $this->email = $assistedPerson->email ?? '';

        $this->postalCode = PostalCode::format($assistedPerson->postal_code);
        $this->street = $assistedPerson->street ?? '';
        $this->number = $assistedPerson->number ?? '';
        $this->complement = $assistedPerson->complement ?? '';
        $this->district = $assistedPerson->district ?? '';
        $this->city = $assistedPerson->city ?? '';
        $this->state = $assistedPerson->state?->value ?? '';
    }

    public function updateAssistedPerson(UpdateAssistedPerson $updateAssistedPerson): void
    {
        Gate::authorize('update', $this->assistedPerson);

        $validated = $this->validate(AssistedPersonRules::all());

        $updateAssistedPerson->handle($this->assistedPerson, $this->assistedPersonAttributes($validated));

        Flux::toast(variant: 'success', text: __('Assisted person updated.'));

        $this->redirectRoute('assisted-people.index', navigate: true);
    }

    public function render()
    {
        return $this->view()->title(__('Edit :name', ['name' => $this->assistedPerson->name]));
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl">{{ __('Edit :name', ['name' => $assistedPerson->name]) }}</flux:heading>
    <flux:subheading>{{ __("Update this assisted person's details for this Spiritist Center") }}</flux:subheading>
    <flux:separator variant="subtle" class="my-4" />

    <form wire:submit="updateAssistedPerson" class="max-w-xl space-y-6">
        <flux:fieldset>
            <flux:input wire:model="name" :label="__('Name')" required autofocus :placeholder="__('John Doe')" data-test="assisted-person-name-input" />
            <flux:input type="email" wire:model="email" :label="__('Email')" :placeholder="__('john.doe@example.com')" data-test="assisted-person-email-input" />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input type="date" wire:model="birthDate" :label="__('Birth date')" max="{{ today()->toDateString() }}" data-test="assisted-person-birth-date-input" />
                <flux:input wire:model="phone" :label="__('Phone')" placeholder="(00) 00000-0000" inputmode="numeric" mask="(99) 99999-9999" data-test="assisted-person-phone-input" />
            </div>
        </flux:fieldset>

        <x-pages::address-form :states="$this->states" test-prefix="assisted-person" />

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('assisted-people.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>

            <flux:button variant="primary" type="submit" data-test="assisted-person-save-button">
                {{ __('Save') }}
            </flux:button>
        </div>
    </form>
</section>
