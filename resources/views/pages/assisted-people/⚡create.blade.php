<?php

use App\Actions\AssistedPeople\CreateAssistedPerson;
use App\Concerns\InteractsWithAddressForm;
use App\Models\AssistedPerson;
use App\Rules\AssistedPersonRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    use InteractsWithAddressForm;

    public string $name = '';

    public string $birthDate = '';

    public string $phone = '';

    public string $email = '';

    public function mount(): void
    {
        Gate::authorize('create', [AssistedPerson::class, Auth::user()->currentTeam]);
    }

    public function createAssistedPerson(CreateAssistedPerson $createAssistedPerson): void
    {
        Gate::authorize('create', [AssistedPerson::class, Auth::user()->currentTeam]);

        $validated = $this->validate(AssistedPersonRules::all());

        $createAssistedPerson->handle(Auth::user()->currentTeam, [
            'name' => $validated['name'],
            'birth_date' => $validated['birthDate'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'postal_code' => $validated['postalCode'] ?? null,
            'street' => $validated['street'] ?? null,
            'number' => $validated['number'] ?? null,
            'complement' => $validated['complement'] ?? null,
            'district' => $validated['district'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
        ]);

        Flux::toast(variant: 'success', text: __('Assisted person created.'));

        $this->redirectRoute('assisted-people.index', navigate: true);
    }

    public function render()
    {
        return $this->view()->title(__('New assisted person'));
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl">{{ __('New assisted person') }}</flux:heading>
    <flux:subheading>{{ __('Register a new assisted person for this Spiritist Center') }}</flux:subheading>
    <flux:separator variant="subtle" class="my-4" />

    <form wire:submit="createAssistedPerson" class="space-y-6">
        <flux:fieldset>
            <flux:input wire:model="name" :label="__('Name')" required autofocus data-test="assisted-person-name-input" class="w-full sm:max-w-sm"/>
            <flux:input type="email" wire:model="email" :label="__('Email')" data-test="assisted-person-email-input" class="w-full sm:max-w-sm" />

            <div class="grid gap-6 sm:grid-cols-6">
                <div class="sm:col-span-2">
                    <flux:input type="date" wire:model="birthDate" :label="__('Birth date')" max="{{ today()->toDateString() }}" data-test="assisted-person-birth-date-input" />
                </div>

                <div class="sm:col-span-2">
                    <flux:input wire:model="phone" :label="__('Phone')" placeholder="(00) 00000-0000" inputmode="numeric" mask="(99) 99999-9999" data-test="assisted-person-phone-input" />
                </div>
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
