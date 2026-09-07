<?php

use App\Models\Team;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Team $team;

    #[Locked]
    public string $invitationCode = '';

    #[Locked]
    public string $invitationEmail = '';

    public function mount(Team $team): void
    {
        $this->team = $team;
    }

    #[On('confirm-cancel-invitation')]
    public function confirmCancelInvitation(string $invitationCode, string $invitationEmail): void
    {
        $this->invitationCode = $invitationCode;
        $this->invitationEmail = $invitationEmail;

        Flux::modal('cancel-invitation')->show();
    }

    public function cancelInvitation(): void
    {
        Gate::authorize('cancelInvitation', $this->team);

        $invitation = $this->team->invitations()->where('code', $this->invitationCode)->firstOrFail();

        $invitation->delete();

        Flux::modal('cancel-invitation')->close();

        $this->dispatch('invitation-cancelled');

        Flux::toast(variant: 'success', text: __('Invitation cancelled.'));
    }
}; ?>

<flux:modal name="cancel-invitation" focusable class="max-w-lg">
    <form wire:submit="cancelInvitation" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Cancel invitation') }}</flux:heading>
            <flux:subheading>
                {{ __('Are you sure you want to cancel the invitation for :email?', ['email' => $invitationEmail]) }}
            </flux:subheading>
        </div>
        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Keep invitation') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" type="submit" wire:loading.attr="disabled" data-test="cancel-invitation-confirm">{{ __('Cancel invitation') }}</flux:button>
        </div>
    </form>
</flux:modal>
