<?php

use App\Actions\Appointments\UpdateAppointment;
use App\Concerns\InteractsWithAppointmentForm;
use App\Models\Appointment;
use App\Rules\AppointmentRules;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    use InteractsWithAppointmentForm;

    public Appointment $appointment;

    public function mount(Appointment $appointment): void
    {
        Gate::authorize('update', $appointment);

        $this->appointment = $appointment;

        $this->appointmentTypeId = (string) $appointment->appointment_type_id;
        $this->mode = $appointment->mode->value;
        $this->assistedPersonId = $appointment->assisted_person_id;
        $this->scheduledOn = $appointment->scheduled_on->toDateString();
        $this->notes = $appointment->notes ?? '';
    }

    public function updateAppointment(UpdateAppointment $updateAppointment): void
    {
        Gate::authorize('update', $this->appointment);

        $validated = $this->validate(AppointmentRules::all($this->appointment->team));

        $updateAppointment->handle($this->appointment, $this->appointmentAttributes($validated));

        Flux::toast(variant: 'success', text: __('Appointment updated.'));

        $this->redirectRoute('appointments.index', navigate: true);
    }

    public function render()
    {
        return $this->view()->title(__('Edit appointment'));
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl">{{ __('Edit appointment') }}</flux:heading>
    <flux:subheading>{{ __('Update the details of this appointment') }}</flux:subheading>
    <flux:separator variant="subtle" class="my-4" />

    <form wire:submit="updateAppointment" class="max-w-xl space-y-6">
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
                {{ __('Save changes') }}
            </flux:button>
        </div>
    </form>

    <livewire:appointments.create-appointment-type-modal />
    <livewire:appointments.quick-create-assisted-person-modal />
</section>
