<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Flux\Flux;
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

    #[On('confirm-remove-member')]
    public function confirmRemoveMember(int $memberId, string $memberName): void
    {
        $this->memberId = $memberId;
        $this->memberName = $memberName;

        Flux::modal('remove-member')->show();
    }

    public function removeMember(): void
    {
        Gate::authorize('removeMember', $this->team);

        $membership = $this->team->memberships()
            ->where('user_id', $this->memberId)
            ->firstOrFail();

        abort_if($membership->role === TeamRole::Owner, 403);

        $user = User::findOrFail($this->memberId);

        $membership->delete();

        if ($user->isCurrentTeam($this->team)) {
            $user->switchTeam($user->personalTeam());
        }

        Flux::modal('remove-member')->close();

        $this->dispatch('member-removed');

        Flux::toast(variant: 'success', text: __('Member removed.'));
    }
}; ?>

<flux:modal name="remove-member" focusable class="max-w-lg">
    <form wire:submit="removeMember" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Remove team member') }}</flux:heading>
            <flux:subheading>
                {{ __('Are you sure you want to remove :name from this team?', ['name' => $memberName]) }}
            </flux:subheading>
        </div>
        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" type="submit" wire:loading.attr="disabled" data-test="remove-member-confirm">{{ __('Remove member') }}</flux:button>
        </div>
    </form>
</flux:modal>
