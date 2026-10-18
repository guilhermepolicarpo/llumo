<?php

use App\Data\TeamPermissions;
use App\Enums\TeamRole;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Team $team;

    public function mount(): void
    {
        $team = Auth::user()->currentTeam;

        Gate::authorize('update', $team);

        $this->team = $team;
    }

    public function updateMember(int $userId, string $role): void
    {
        Gate::authorize('updateMember', $this->team);

        $validated = Validator::make(['role' => $role], [
            'role' => ['required', 'string', Rule::enum(TeamRole::class)->except([TeamRole::Owner])],
        ])->validate();

        $membership = $this->team->memberships()
            ->where('user_id', $userId)
            ->firstOrFail();

        abort_if($membership->role === TeamRole::Owner, 403);

        $membership->update(['role' => TeamRole::from($validated['role'])]);

        Flux::toast(variant: 'success', text: __('Member role updated.'));
    }

    #[Computed]
    public function permissions(): TeamPermissions
    {
        return Auth::user()->toTeamPermissions($this->team);
    }

    #[Computed]
    public function members(): Collection
    {
        return $this->team->members()->get();
    }

    #[Computed]
    public function invitations(): Collection
    {
        return $this->team->invitations()->whereNull('accepted_at')->get();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function availableRoles(): array
    {
        return TeamRole::assignable();
    }

    public function render()
    {
        return $this->view()->title(__('Members'));
    }
}; ?>

<section class="w-full">
    @include('partials.team-settings-heading')

    <x-pages::team-settings.layout :heading="__('Members')" :subheading="__('Manage who belongs to this Spiritist Center')">
        @if ($this->permissions->canCreateInvitation)
            <x-slot:actions>
                <flux:modal.trigger name="invite-member">
                    <flux:button variant="primary" icon="user-plus" class="shrink-0" data-test="invite-member-button">
                        {{ __('Invite member') }}
                    </flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        @endif

        <div class="space-y-10">
            <div class="space-y-3">
                <flux:card class="divide-y divide-zinc-200 p-0 dark:divide-zinc-700">
                    @foreach ($this->members as $member)
                        <div wire:key="member-{{ $member->id }}" class="flex items-center justify-between px-4 py-3" data-test="member-row">
                            <div class="flex items-center gap-4">
                                <flux:avatar :name="$member->name" :initials="$member->initials()" />
                                <div>
                                    <div class="font-medium">{{ $member->name }}</div>
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $member->email }}</flux:text>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($member->pivot->role !== TeamRole::Owner && $this->permissions->canUpdateMember)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="outline" size="sm" icon:trailing="chevron-down" data-test="member-role-trigger">
                                            {{ $member->pivot->role->label() }}
                                        </flux:button>
                                        <flux:menu>
                                            @foreach ($this->availableRoles as $role)
                                                <flux:menu.item
                                                    as="button"
                                                    type="button"
                                                    wire:click="updateMember({{ $member->id }}, '{{ $role['value'] }}')"
                                                    data-test="member-role-option"
                                                >
                                                    {{ $role['label'] }}
                                                </flux:menu.item>
                                            @endforeach
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <flux:badge color="zinc">{{ $member->pivot->role->label() }}</flux:badge>
                                @endif

                                @if ($member->pivot->role !== TeamRole::Owner && $this->permissions->canRemoveMember)
                                    <flux:tooltip :content="__('Remove member')">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="x-mark"
                                            :aria-label="__('Remove member')"
                                            wire:click="$dispatch('confirm-remove-member', { memberId: {{ $member->id }}, memberName: @js($member->name) })"
                                            data-test="member-remove-button"
                                        />
                                    </flux:tooltip>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </flux:card>

                <flux:text class="text-sm">
                    {{ __('Owners and admins edit the center details, invite members and manage the catalogs. Members use the system but do not see this area.') }}
                </flux:text>
            </div>

            <div class="space-y-6">
                <div>
                    <flux:heading>{{ __('Pending invitations') }}</flux:heading>
                    <flux:subheading>{{ __('Invitations that have not been accepted yet') }}</flux:subheading>
                </div>

                @if ($this->invitations->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($this->invitations as $invitation)
                            <div wire:key="invitation-{{ $invitation->code }}" class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="invitation-row">
                                <div class="flex items-center gap-4">
                                    <div class="flex size-10 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon name="envelope" class="text-zinc-500" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $invitation->email }}</div>
                                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $invitation->role->label() }}</flux:text>
                                    </div>
                                </div>

                                @if ($this->permissions->canCancelInvitation)
                                    <flux:tooltip :content="__('Cancel invitation')">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="x-mark"
                                            :aria-label="__('Cancel invitation')"
                                            wire:click="$dispatch('confirm-cancel-invitation', { invitationCode: @js($invitation->code), invitationEmail: @js($invitation->email) })"
                                            data-test="invitation-cancel-button"
                                        />
                                    </flux:tooltip>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-lg border border-dashed border-zinc-300 p-5 text-center dark:border-zinc-600" data-test="invitations-empty">
                        <flux:text>{{ __('No pending invitations.') }}</flux:text>
                    </div>
                @endif
            </div>
        </div>
    </x-pages::team-settings.layout>

    @if ($this->permissions->canCreateInvitation)
        <livewire:pages::teams.invite-member-modal :team="$team" @invitation-created="$refresh" />
    @endif

    @if ($this->permissions->canRemoveMember)
        <livewire:pages::teams.remove-member-modal :team="$team" @member-removed="$refresh" />
    @endif

    @if ($this->permissions->canCancelInvitation)
        <livewire:pages::teams.cancel-invitation-modal :team="$team" @invitation-cancelled="$refresh" />
    @endif
</section>
