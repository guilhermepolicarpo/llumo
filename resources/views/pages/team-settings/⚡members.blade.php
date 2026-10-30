<?php

use App\Actions\Teams\SendTeamInvitation;
use App\Data\TeamPermissions;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
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
        Gate::authorize('updateMember', [$this->team, User::findOrFail($userId)]);

        $validated = Validator::make(['role' => $role], [
            'role' => ['required', 'string', Rule::enum(TeamRole::class)->except([TeamRole::Owner])],
        ])->validate();

        $membership = $this->team->memberships()
            ->where('user_id', $userId)
            ->firstOrFail();

        $membership->update(['role' => TeamRole::from($validated['role'])]);

        Flux::toast(variant: 'success', text: __('Member role updated.'));
    }

    #[Renderless]
    public function resendInvitation(string $invitationCode, SendTeamInvitation $sendTeamInvitation): void
    {
        Gate::authorize('inviteMember', $this->team);

        $invitation = $this->team->invitations()
            ->whereNull('accepted_at')
            ->where('code', $invitationCode)
            ->firstOrFail();

        $sendTeamInvitation->handle($invitation);

        Flux::toast(variant: 'success', text: __('Invitation resent.'));
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
                        <div wire:key="member-{{ $member->id }}" class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between" data-test="member-row">
                            <div class="flex min-w-0 items-center gap-4">
                                <flux:avatar :name="$member->name" :initials="$member->initials()" class="shrink-0" />
                                <div class="min-w-0">
                                    <div class="truncate font-medium">{{ $member->name }}</div>
                                    <flux:text class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $member->email }}</flux:text>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2 ps-14 sm:ps-0">
                                @php($isManageable = $member->pivot->role !== TeamRole::Owner && $member->id !== auth()->id())

                                @if ($isManageable && $this->permissions->canUpdateMember)
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

                                            @if ($this->permissions->canTransferOwnership)
                                                <flux:menu.separator />

                                                <flux:menu.item
                                                    as="button"
                                                    type="button"
                                                    icon="key"
                                                    icon:variant="outline"
                                                    wire:click="$dispatch('confirm-transfer-ownership', { memberId: {{ $member->id }}, memberName: @js($member->name) })"
                                                    data-test="member-transfer-ownership"
                                                >
                                                    {{ __('Transfer ownership') }}
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <flux:badge color="zinc">{{ $member->pivot->role->label() }}</flux:badge>
                                @endif

                                @if ($isManageable && $this->permissions->canRemoveMember)
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
                    {{ __('Owners and admins edit the center details, manage members and invitations, and manage the catalogs. Members use the system but do not see this area.') }}
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
                            <div wire:key="invitation-{{ $invitation->code }}" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="invitation-row">
                                <div class="flex min-w-0 items-center gap-4">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon name="envelope" class="text-zinc-500" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate font-medium">{{ $invitation->email }}</div>
                                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $invitation->role->label() }}</flux:text>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-1">
                                    @if ($this->permissions->canCreateInvitation)
                                        <flux:tooltip :content="__('Resend invitation')">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="arrow-path"
                                                icon:variant="outline"
                                                :aria-label="__('Resend invitation')"
                                                wire:click="resendInvitation(@js($invitation->code))"
                                                wire:loading.attr="disabled"
                                                wire:target="resendInvitation"
                                                data-test="invitation-resend-button"
                                            />
                                        </flux:tooltip>
                                    @endif

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

    @if ($this->permissions->canTransferOwnership)
        <livewire:pages::teams.transfer-ownership-modal :team="$team" @ownership-transferred="$refresh" />
    @endif

    @if ($this->permissions->canCancelInvitation)
        <livewire:pages::teams.cancel-invitation-modal :team="$team" @invitation-cancelled="$refresh" />
    @endif
</section>
