<?php

use App\Enums\AppointmentAction;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?int $appointmentId = null;

    #[Locked]
    public ?AppointmentAction $action = null;

    public string $appointmentDescription = '';

    #[On('confirm-appointment-action')]
    public function confirmAppointmentAction(int $appointmentId, AppointmentAction $action, string $appointmentDescription): void
    {
        $this->appointmentId = $appointmentId;
        $this->action = $action;
        $this->appointmentDescription = $appointmentDescription;

        Flux::modal('confirm-appointment-action')->show();
    }

    #[Renderless]
    public function confirm(): void
    {
        Flux::modal('confirm-appointment-action')->close();

        $this->dispatch('appointment-action-confirmed', appointmentId: $this->appointmentId, action: $this->action?->value);
    }
}; ?>

<flux:modal name="confirm-appointment-action" focusable class="max-w-lg">
    <form wire:submit="confirm" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $action?->label() }}</flux:heading>
            <flux:subheading>
                {{ $action?->confirmation($appointmentDescription) }}
            </flux:subheading>
        </div>
        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Back') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" type="submit" wire:loading.attr="disabled" data-test="confirm-appointment-action-confirm">
                {{ $action?->label() }}
            </flux:button>
        </div>
    </form>
</flux:modal>
