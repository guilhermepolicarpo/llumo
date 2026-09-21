<?php

use App\Actions\Appointments\CreateAppointment;
use App\Concerns\InteractsWithAppointmentForm;
use App\Models\Appointment;
use App\Rules\AppointmentRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    use InteractsWithAppointmentForm;

    public function mount(): void
    {
        Gate::authorize('create', [Appointment::class, Auth::user()->currentTeam]);

        $this->scheduledOn = today()->toDateString();
    }

    public function createAppointment(CreateAppointment $createAppointment): void
    {
        $team = Auth::user()->currentTeam;

        Gate::authorize('create', [Appointment::class, $team]);

        $validated = $this->validate(AppointmentRules::all($team));

        $createAppointment->handle($team, $this->appointmentAttributes($validated));

        Flux::toast(variant: 'success', text: __('Appointment created.'));

        $this->redirectRoute('appointments.index', navigate: true);
    }

    public function render()
    {
        return $this->view()->title(__('New appointment'));
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl">{{ __('New appointment') }}</flux:heading>
    <flux:subheading>{{ __('Schedule an assisted person for an appointment') }}</flux:subheading>
    <flux:separator variant="subtle" class="my-4" />

    <form wire:submit="createAppointment" class="max-w-xl space-y-6">
        <x-pages::appointments.form
            :appointment-types="$this->appointmentTypes"
            :modes="$this->modes"
            :selected-assisted-person="$this->selectedAssistedPerson"
            :assisted-person-search="$assistedPersonSearch"
            :assisted-person-suggestions="$this->assistedPersonSuggestions"
        />

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('appointments.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>

            <flux:button variant="primary" type="submit" data-test="appointment-save-button">
                {{ __('Register appointment') }}
            </flux:button>
        </div>
    </form>

    <livewire:appointments.create-appointment-type-modal />
    <livewire:appointments.quick-create-assisted-person-modal />
</section>
