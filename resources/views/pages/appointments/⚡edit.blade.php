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
    @php([$primaryActions, $secondaryActions] = collect(AppointmentAction::availableFor($appointment))->partition(fn (AppointmentAction $action) => $action->isPrimary()))
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Edit appointment') }}</flux:heading>
            <flux:subheading>{{ __('Update the details of this appointment') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            @foreach ($primaryActions as $action)
                <flux:button
                    :icon="$action->icon()"
                    wire:click="perform({{ $appointment->id }}, '{{ $action->value }}')"
                    wire:loading.attr="disabled"
                    data-test="appointment-action-{{ $action->value }}"
                    >
                    {{ $action->label() }}
                </flux:button>
            @endforeach

            <flux:dropdown position="bottom" align="end">
                <flux:button variant="ghost" icon="ellipsis-horizontal" :aria-label="__('More actions')" data-test="appointment-actions-trigger" />
                <flux:menu>
                    <x-pages::appointments.action-menu-items :appointment="$appointment" :actions="$secondaryActions" />

                    @if ($secondaryActions->isNotEmpty())
                        <flux:menu.separator />
                    @endif

                    <flux:menu.item
                        variant="danger"
                        icon="trash"
                        wire:click="$dispatch('confirm-delete-appointment', { appointmentId: {{ $appointment->id }}, appointmentDescription: @js($appointment->description) })"
                        data-test="appointment-delete-menu-item"
                        >
                        {{ __('Delete') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>
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
    <livewire:appointments.confirm-appointment-action-modal />
    <livewire:appointments.delete-appointment-modal @appointment-deleted="appointmentDeleted" />
</section>
