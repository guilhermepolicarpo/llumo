<?php

use App\Actions\Teams\TransferTeamOwnership;
use App\Models\Team;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Team $team;

    #[Locked]
    public ?int $memberId = null;

    #[Locked]
    public string $memberName = '';

    public function mount(Team $team): void
    {
        $this->team = $team;
    }

    #[On('confirm-transfer-ownership')]
    public function confirmTransferOwnership(int $memberId, string $memberName): void
    {
        $this->memberId = $memberId;
        $this->memberName = $memberName;

        Flux::modal('transfer-ownership')->show();
    }

    public function transferOwnership(TransferTeamOwnership $transferTeamOwnership): void
    {
        $newOwner = User::findOrFail($this->memberId);

        Gate::authorize('transferOwnership', [$this->team, $newOwner]);

        $transferTeamOwnership->handle($this->team, Auth::user(), $newOwner);

        Flux::modal('transfer-ownership')->close();

        $this->dispatch('ownership-transferred');

        Flux::toast(variant: 'success', text: __('Ownership transferred.'));
    }
}; ?>

<flux:modal name="transfer-ownership" focusable class="max-w-lg">
    <form wire:submit="transferOwnership" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Transfer ownership') }}</flux:heading>
            <flux:subheading>
                {{ __(':name will become the owner of this Spiritist Center and you will become an admin. Only the new owner will be able to undo this.', ['name' => $memberName]) }}
            </flux:subheading>
        </div>
        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" type="submit" wire:loading.attr="disabled" data-test="transfer-ownership-confirm">{{ __('Transfer ownership') }}</flux:button>
        </div>
    </form>
</flux:modal>
