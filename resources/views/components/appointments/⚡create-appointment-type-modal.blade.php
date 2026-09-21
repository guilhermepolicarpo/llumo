<?php

use App\Actions\AppointmentTypes\CreateAppointmentType;
use App\Models\AppointmentType;
use App\Rules\AppointmentTypeRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component {
    public string $name = '';

    public bool $requiresRecord = false;

    public function createAppointmentType(CreateAppointmentType $createAppointmentType): void
    {
        $team = Auth::user()->currentTeam;

        Gate::authorize('create', [AppointmentType::class, $team]);

        $this->name = trim($this->name);

        $validated = $this->validate([
            'name' => AppointmentTypeRules::name($team),
            'requiresRecord' => AppointmentTypeRules::requiresRecord(),
        ]);

        $appointmentType = $createAppointmentType->handle($team, [
            'name' => $validated['name'],
            'requires_record' => $validated['requiresRecord'],
        ]);

        $this->reset('name', 'requiresRecord');

        Flux::modal('create-appointment-type')->close();

        $this->dispatch('appointment-type-created', appointmentTypeId: $appointmentType->id);

        Flux::toast(variant: 'success', text: __('Appointment type created.'));
    }
}; ?>

<flux:modal name="create-appointment-type" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form wire:submit="createAppointmentType" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('New appointment type') }}</flux:heading>
            <flux:subheading>{{ __('Register a type of appointment offered by this Spiritist Center.') }}</flux:subheading>
        </div>

        <flux:input wire:model="name" :label="__('Name')" :placeholder="__('Fraternal assistance')" required autofocus data-test="appointment-type-name-input" />

        <flux:switch
            wire:model="requiresRecord"
            :label="__('Fill in a record during the appointment')"
            :description="__('Opens the appointment record screen when the assisted person is attended.')"
            align="left"
            data-test="appointment-type-requires-record-switch"
        />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" type="submit" wire:loading.attr="disabled" data-test="appointment-type-save-button">{{ __('Save') }}</flux:button>
        </div>
    </form>
</flux:modal>
