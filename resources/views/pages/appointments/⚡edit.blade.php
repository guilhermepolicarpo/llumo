<?php

use App\Actions\Appointments\PerformAppointmentAction;
use App\Actions\Appointments\UpdateAppointment;
use App\Concerns\InteractsWithAppointmentActions;
use App\Concerns\InteractsWithAppointmentForm;
use App\Enums\AppointmentAction;
use App\Models\Appointment;
use App\Rules\AppointmentRules;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    use InteractsWithAppointmentActions;
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

    /**
     * Perform the action on this appointment and leave the edit page, which only applies while it is scheduled.
     */
    #[On('appointment-action-confirmed')]
    public function perform(PerformAppointmentAction $performAppointmentAction, int $appointmentId, ?AppointmentAction $action): void
    {
        abort_unless($appointmentId === $this->appointment->id, 404);

        if ($this->performAppointmentAction($performAppointmentAction, $this->appointment, $action)) {
            $this->redirectRoute('appointments.index', navigate: true);
        }
    }

    public function appointmentDeleted(): void
    {
        $this->redirectRoute('appointments.index', navigate: true);
    }

    public function render()
    {
        return $this->view()->title(__('Edit appointment'));
    }
}; ?>

<section class="w-full">
    <x-page-header :heading="__('Edit appointment')" :subheading="__('Update the details of this appointment')" :back-href="route('appointments.index')" :back-label="__('Back to appointments')">
        <x-pages::appointments.action-buttons :appointment="$appointment" :edit-as="null" />
    </x-page-header>

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
    <livewire:appointments.confirm-appointment-action-modal />
    <livewire:appointments.delete-appointment-modal @appointment-deleted="appointmentDeleted" />
</section>
