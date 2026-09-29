<?php

use App\Actions\Appointments\CreateAppointment;
use App\Concerns\InteractsWithAppointmentForm;
use App\Models\Appointment;
use App\Rules\AppointmentRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

new class extends Component
{
    use InteractsWithAppointmentForm;

    public function mount(): void
    {
        Gate::authorize('create', [Appointment::class, Auth::user()->currentTeam]);

        $this->scheduledOn = today()->toDateString();

        $this->prefillFromQuery();
    }

    /**
     * Pre-select the assisted person and the date given in the link that opened the form, such as the one scheduling a return.
     */
    private function prefillFromQuery(): void
    {
        $prefill = Validator::make(request()->query(), [
            'assisted_person' => AppointmentRules::assistedPersonId(Auth::user()->currentTeam),
            'date' => AppointmentRules::scheduledOn(),
        ])->valid();

        if (isset($prefill['assisted_person'])) {
            $this->selectAssistedPerson((int) $prefill['assisted_person']);
        }

        if (isset($prefill['date'])) {
            $this->scheduledOn = Date::parse($prefill['date'])->toDateString();
        }
    }

    public function createAppointment(CreateAppointment $createAppointment): void
    {
        $team = Auth::user()->currentTeam;

        Gate::authorize('create', [Appointment::class, $team]);

        $validated = $this->validate(AppointmentRules::all($team));

        $createAppointment->handle($team, Auth::user(), $this->appointmentAttributes($validated));

        Flux::toast(variant: 'success', text: __('Appointment created.'));

        $this->redirectRoute('appointments.index', navigate: true);
    }

    public function render()
    {
        return $this->view()->title(__('New appointment'));
    }
}; ?>

<section class="w-full">
    <x-page-header :heading="__('New appointment')" :subheading="__('Schedule an assisted person for an appointment')" :back-href="route('appointments.index')" :back-label="__('Back to appointments')" />

    <form wire:submit="createAppointment" class="max-w-xl space-y-6">
        <x-pages::appointments.form
            :appointment-types="$this->appointmentTypes"
            :weekday-restricted-types="$this->weekdayRestrictedAppointmentTypes"
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
    <livewire:appointments.create-assisted-person-modal />
</section>
