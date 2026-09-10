<?php

use App\Actions\Teams\UpdateTeamProfile;
use App\Concerns\InteractsWithAddressForm;
use App\Data\TeamPermissions;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Rules\PostalCode;
use App\Rules\TeamProfileRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use InteractsWithAddressForm, WithFileUploads;

    public Team $team;

    public string $teamName = '';

    public ?TemporaryUploadedFile $logo = null;

    public function mount(Team $team): void
    {
        $this->team = $team;
        $this->teamName = $team->name;

        $this->postalCode = PostalCode::format($team->postal_code);
        $this->street = $team->street ?? '';
        $this->number = $team->number ?? '';
        $this->complement = $team->complement ?? '';
        $this->district = $team->district ?? '';
        $this->city = $team->city ?? '';
        $this->state = $team->state?->value ?? '';
    }

    public function updateTeam(UpdateTeamProfile $updateTeamProfile): void
    {
        Gate::authorize('update', $this->team);

        $validated = $this->validate(TeamProfileRules::all());

        $this->team = $updateTeamProfile->handle(
            $this->team,
            [
                'name' => $validated['teamName'],
                'postal_code' => $validated['postalCode'] ?? null,
                'street' => $validated['street'] ?? null,
                'number' => $validated['number'] ?? null,
                'complement' => $validated['complement'] ?? null,
                'district' => $validated['district'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
            ],
            logo: $this->logo,
        );

        $this->reset('logo');

        Flux::toast(variant: 'success', text: __('Spiritist Center updated.'));

        if ($this->team->wasChanged('slug')) {
            $this->redirectRoute('teams.edit', ['team' => $this->team->slug], navigate: true);
        }
    }

    public function removeLogo(UpdateTeamProfile $updateTeamProfile): void
    {
        Gate::authorize('update', $this->team);

        $this->team = $updateTeamProfile->removeLogo($this->team);

        $this->reset('logo');

        Flux::toast(variant: 'success', text: __('Logo removed.'));
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

    #[On('invitation-created')]
    #[On('invitation-cancelled')]
    #[On('member-removed')]
    public function refreshTeamData(): void
    {
        // Listening is enough: it forces a re-render, and the computed properties recompute fresh each request.
    }

    public function render()
    {
        $teamName = $this->team->name;

        $title = $this->permissions->canUpdateTeam
            ? __('Edit :name', ['name' => $teamName])
            : __('View :name', ['name' => $teamName]);

        return $this->view()->title($title);
    }

    #[Computed]
    public function permissions(): TeamPermissions
    {
        return Auth::user()->toTeamPermissions($this->team);
    }

    #[Computed]
    public function members(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->team->members()->get();
    }

    #[Computed]
    public function invitations(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->team->invitations()->whereNull('accepted_at')->get();
    }

    #[Computed]
    public function availableRoles(): array
    {
        return TeamRole::assignable();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Spiritist Centers') }}</flux:heading>

    <x-pages::settings.layout>
        <div x-data="{ tab: 'info' }" wire:key="team-tabs" class="space-y-6">
            <div class="flex gap-6 border-b border-zinc-200 dark:border-zinc-700" role="tablist" aria-label="{{ __('Spiritist Centers') }}">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="(tab === 'info').toString()"
                    @click="tab = 'info'"
                    :class="tab === 'info' ? 'border-zinc-950 text-zinc-950 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'"
                    class="-mb-px border-b-2 pb-3 text-sm font-medium transition"
                    data-test="team-tab-info"
                >
                    {{ __('Info') }}
                </button>

                <button
                    type="button"
                    role="tab"
                    :aria-selected="(tab === 'members').toString()"
                    @click="tab = 'members'"
                    :class="tab === 'members' ? 'border-zinc-950 text-zinc-950 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'"
                    class="-mb-px border-b-2 pb-3 text-sm font-medium transition"
                    data-test="team-tab-members"
                >
                    {{ __('Members') }}
                </button>
            </div>

            <div x-show="tab === 'info'" x-cloak role="tabpanel" class="space-y-10">
            <div>
                <flux:heading>{{ __('Spiritist Center Info') }}</flux:heading>
                <flux:subheading>{{ __('Manage your Spiritist Center logo, name, and address') }}</flux:subheading>
            </div>

            <div class="space-y-6">
                @if ($this->permissions->canUpdateTeam)
                    <div class="space-y-4">
                        <form wire:submit="updateTeam" class="space-y-6">
                            <flux:fieldset>
                                <flux:field>
                                    <flux:label>{{ __('Logo') }}</flux:label>

                                    <div class="flex items-center gap-4">
                                        <flux:avatar
                                            size="xl"
                                            :src="$logo?->isPreviewable() ? $logo->temporaryUrl() : $team->logo_url"
                                            :name="$team->name"
                                            data-test="team-logo-preview"
                                        />

                                        <div class="flex flex-1 flex-col gap-2">
                                            <flux:input
                                                type="file"
                                                wire:model="logo"
                                                accept="image/png,image/jpeg,image/webp"
                                                data-test="team-logo-input"
                                            />

                                            <div class="flex items-center gap-3">
                                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ __('PNG, JPG or WEBP up to 2 MB.') }}
                                                </flux:text>

                                                <flux:text wire:loading wire:target="logo" class="text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ __('Uploading...') }}
                                                </flux:text>

                                                @if ($team->logo_url)
                                                    <flux:button
                                                        variant="ghost"
                                                        size="sm"
                                                        type="button"
                                                        wire:click="removeLogo"
                                                        data-test="team-logo-remove-button"
                                                    >
                                                        {{ __('Remove logo') }}
                                                    </flux:button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <flux:error name="logo" />
                                </flux:field>

                                <flux:input wire:model="teamName" :label="__('Spiritist Center name')" required data-test="team-name-input" />
                            </flux:fieldset>

                            <x-pages::address-form :states="$this->states" test-prefix="team" />

                            <flux:button variant="primary" type="submit" data-test="team-save-button">
                                {{ __('Save') }}
                            </flux:button>
                        </form>
                    </div>
                @else
                    <div class="flex items-center gap-4">
                        <flux:avatar size="lg" :src="$team->logo_url" :name="$team->name" data-test="team-logo-preview" />

                        <div>
                            <flux:heading>{{ $team->name }}</flux:heading>

                            @if ($team->formatted_address)
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400" data-test="team-formatted-address">
                                    {{ $team->formatted_address }}
                                </flux:text>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            @if ($this->permissions->canDeleteTeam && ! $team->is_personal)
                <div class="space-y-6">
                    <div>
                        <flux:heading>{{ __('Delete Spiritist Center') }}</flux:heading>
                        <flux:subheading>{{ __('Permanently delete your Spiritist Center') }}</flux:subheading>
                    </div>

                    <div class="space-y-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-200/10 dark:bg-red-900/20 dark:text-red-100">
                        <div>
                            <p class="font-medium">{{ __('Warning') }}</p>
                            <p class="text-sm">{{ __('Please proceed with caution, this cannot be undone.') }}</p>
                        </div>

                        <flux:modal.trigger name="delete-team">
                            <flux:button variant="danger" data-test="delete-team-button">
                                {{ __('Delete Spiritist Center') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>
            @endif
            </div>

            <div x-show="tab === 'members'" x-cloak role="tabpanel" class="space-y-10">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading>{{ __('Spiritist Center members') }}</flux:heading>
                        @if ($this->permissions->canAddMember || $this->permissions->canUpdateMember || $this->permissions->canRemoveMember)
                            <flux:subheading>{{ __('Manage who belongs to this Spiritist Center') }}</flux:subheading>
                        @endif
                    </div>

                    @if ($this->permissions->canCreateInvitation)
                        <flux:modal.trigger name="invite-member">
                            <flux:button variant="primary" icon="user-plus" data-test="invite-member-button">
                                {{ __('Invite member') }}
                            </flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>

                <div class="space-y-3">
                    @foreach ($this->members as $member)
                        <div wire:key="member-{{ $member->id }}" class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="member-row">
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
                                            wire:click="$dispatch('confirm-remove-member', { memberId: {{ $member->id }}, memberName: @js($member->name) })"
                                            data-test="member-remove-button"
                                        />
                                    </flux:tooltip>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($this->invitations->isNotEmpty())
                <div class="space-y-6">
                    <div>
                        <flux:heading>{{ __('Pending invitations') }}</flux:heading>
                        <flux:subheading>{{ __('Invitations that have not been accepted yet') }}</flux:subheading>
                    </div>

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
                                            wire:click="$dispatch('confirm-cancel-invitation', { invitationCode: @js($invitation->code), invitationEmail: @js($invitation->email) })"
                                            data-test="invitation-cancel-button"
                                        />
                                    </flux:tooltip>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            </div>
        </div>
    </x-pages::settings.layout>

    @if ($this->permissions->canCreateInvitation)
        <livewire:pages::teams.invite-member-modal :team="$team" />
    @endif

    @if ($this->permissions->canDeleteTeam && ! $team->is_personal)
        <livewire:pages::teams.delete-team-modal :team="$team" />
    @endif

    @if ($this->permissions->canRemoveMember)
        <livewire:pages::teams.remove-member-modal :team="$team" />
    @endif

    @if ($this->permissions->canCancelInvitation)
        <livewire:pages::teams.cancel-invitation-modal :team="$team" />
    @endif
</section>
