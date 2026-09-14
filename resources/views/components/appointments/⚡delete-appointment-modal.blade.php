<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?int $appointmentId = null;

    public string $appointmentDescription = '';

    #[On('confirm-delete-appointment')]
    public function confirmDeleteAppointment(int $appointmentId, string $appointmentDescription): void
    {
        $this->appointmentId = $appointmentId;
        $this->appointmentDescription = $appointmentDescription;

        Flux::modal('delete-appointment')->show();
    }

    public function deleteAppointment(): void
    {
        $appointment = Auth::user()->currentTeam->appointments()->findOrFail($this->appointmentId);

        Gate::authorize('delete', $appointment);

        $appointment->delete();

        Flux::modal('delete-appointment')->close();

        $this->dispatch('appointment-deleted');

        Flux::toast(variant: 'success', text: __('Appointment deleted.'));
    }
}; ?>

<flux:modal name="delete-appointment" focusable class="max-w-lg">
    <form wire:submit="deleteAppointment" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete appointment') }}</flux:heading>
            <flux:subheading>
                {{ __('Are you sure you want to delete the appointment of :description? This action cannot be undone.', ['description' => $appointmentDescription]) }}
            </flux:subheading>
        </div>
        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" type="submit" wire:loading.attr="disabled" data-test="delete-appointment-confirm">
                {{ __('Delete appointment') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
