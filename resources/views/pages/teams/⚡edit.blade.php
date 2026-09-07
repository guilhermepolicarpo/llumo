<?php

use App\Actions\Teams\FetchAddressByPostalCode;
use App\Actions\Teams\UpdateTeamProfile;
use App\Data\TeamPermissions;
use App\Enums\BrazilianState;
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
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Team $teamModel;

    public string $teamName = '';

    public ?TemporaryUploadedFile $logo = null;

    public string $postalCode = '';

    public string $street = '';

    public string $number = '';

    public string $complement = '';

    public string $district = '';

    public string $city = '';

    public string $state = '';

    public array $teamData = [];

    public array $members = [];

    public array $invitations = [];

    public array $availableRoles = [];

    public bool $isCurrentTeam = false;

    public function mount(Team $team): void
    {
        $this->teamModel = $team;
        $this->teamName = $team->name;

        $this->postalCode = PostalCode::format($team->postal_code);
        $this->street = $team->street ?? '';
        $this->number = $team->number ?? '';
        $this->complement = $team->complement ?? '';
        $this->district = $team->district ?? '';
        $this->city = $team->city ?? '';
        $this->state = $team->state?->value ?? '';

        $this->populateTeamData();
    }

    public function updateTeam(UpdateTeamProfile $updateTeamProfile): void
    {
        Gate::authorize('update', $this->teamModel);

        $validated = $this->validate(TeamProfileRules::all());

        $this->teamModel = $updateTeamProfile->handle(
            $this->teamModel,
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

        $this->populateTeamData();

        Flux::toast(variant: 'success', text: __('Team updated.'));

        $this->redirectRoute('teams.edit', ['team' => $this->teamModel->slug], navigate: true);
    }

    public function removeLogo(UpdateTeamProfile $updateTeamProfile): void
    {
        Gate::authorize('update', $this->teamModel);

        $this->teamModel = $updateTeamProfile->removeLogo($this->teamModel);

        $this->reset('logo');

        $this->populateTeamData();

        Flux::toast(variant: 'success', text: __('Logo removed.'));
    }

    /**
     * Fill the blank address fields from the postal code lookup.
     */
    public function updatedPostalCode(string $value): void
    {
        $address = app(FetchAddressByPostalCode::class)->handle($value);

        if ($address === null) {
            return;
        }

        foreach (['street', 'district', 'city', 'state'] as $field) {
            if ($this->{$field} === '' && $address[$field] !== null) {
                $this->{$field} = $address[$field];
            }
        }
    }

    public function updateMember(int $userId, string $role): void
    {
        Gate::authorize('updateMember', $this->teamModel);

        $validated = Validator::make(['role' => $role], [
            'role' => ['required', 'string', Rule::enum(TeamRole::class)],
        ])->validate();

        $this->teamModel->memberships()
            ->where('user_id', $userId)
            ->firstOrFail()
            ->update(['role' => TeamRole::from($validated['role'])]);

        $this->populateTeamData();

        Flux::toast(variant: 'success', text: __('Member role updated.'));
    }

    private function populateTeamData(): void
    {
        $user = Auth::user();

        $team = $this->teamModel;

        $this->teamData = [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'is_personal' => $team->is_personal,
            'logo_url' => $team->logo_url,
            'formatted_address' => $team->formatted_address,
        ];

        $this->members = $team->members()->get()->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'avatar' => $member->avatar ?? null,
            'initials' => $member->initials(),
            'role' => $member->pivot->role->value,
            'role_label' => $member->pivot->role->label(),
        ])->toArray();

        $this->invitations = $team->invitations()
            ->whereNull('accepted_at')
            ->get()
            ->map(fn ($invitation) => [
                'code' => $invitation->code,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'created_at' => $invitation->created_at->toISOString(),
            ])->toArray();

        $this->availableRoles = TeamRole::assignable();

        $this->isCurrentTeam = $user->isCurrentTeam($team);
    }

    public function render()
    {
        $teamName = $this->teamData['name'] ?? $this->teamModel->name;

        $title = $this->permissions->canUpdateTeam
            ? __('Edit :name', ['name' => $teamName])
            : __('View :name', ['name' => $teamName]);

        return $this->view()->title($title);
    }

    #[Computed]
    public function permissions(): TeamPermissions
    {
        return Auth::user()->toTeamPermissions($this->teamModel);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function states(): array
    {
        return BrazilianState::options();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Teams') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Teams')" :subheading="__('Manage your team settings')">
        <div class="space-y-10">
            <div class="space-y-6">
                @if ($this->permissions->canUpdateTeam)
                    <div class="space-y-4">
                        <form wire:submit="updateTeam" class="space-y-6">
                            <flux:field>
                                <flux:label>{{ __('Logo') }}</flux:label>

                                <div class="flex items-center gap-4">
                                    <flux:avatar
                                        size="lg"
                                        :src="$logo?->isPreviewable() ? $logo->temporaryUrl() : $teamData['logo_url']"
                                        :name="$teamData['name']"
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

                                            @if ($teamData['logo_url'])
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

                            <flux:input wire:model="teamName" :label="__('Team name')" required data-test="team-name-input" />

                            <div class="space-y-6">
                                <div>
                                    <flux:heading>{{ __('Address') }}</flux:heading>
                                    <flux:subheading>{{ __('Fill in the postal code to complete the address automatically') }}</flux:subheading>
                                </div>

                                <div class="grid gap-6 sm:grid-cols-6">
                                    <div class="sm:col-span-2">
                                        <flux:input
                                            wire:model.live.blur="postalCode"
                                            :label="__('Postal code')"
                                            placeholder="00000-000"
                                            inputmode="numeric"
                                            mask="99999-999"
                                            data-test="team-postal-code-input"
                                        />

                                        <flux:text wire:loading wire:target="postalCode" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Looking up address...') }}
                                        </flux:text>
                                    </div>

                                    <div class="sm:col-span-4">
                                        <flux:input wire:model="street" :label="__('Street')" data-test="team-street-input" />
                                    </div>

                                    <div class="sm:col-span-2">
                                        <flux:input wire:model="number" :label="__('Number')" data-test="team-number-input" />
                                    </div>

                                    <div class="sm:col-span-4">
                                        <flux:input wire:model="complement" :label="__('Complement')" data-test="team-complement-input" />
                                    </div>

                                    <div class="sm:col-span-2">
                                        <flux:input wire:model="district" :label="__('District')" data-test="team-district-input" />
                                    </div>

                                    <div class="sm:col-span-2">
                                        <flux:input wire:model="city" :label="__('City')" data-test="team-city-input" />
                                    </div>

                                    <div class="sm:col-span-2">
                                        <flux:select
                                            wire:model="state"
                                            :label="__('State')"
                                            :placeholder="__('UF')"
                                            data-test="team-state-select"
                                        >
                                            @foreach ($this->states as $stateOption)
                                                <flux:select.option :value="$stateOption['value']">
                                                    {{ $stateOption['label'] }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                </div>
                            </div>

                            <flux:button variant="primary" type="submit" data-test="team-save-button">
                                {{ __('Save') }}
                            </flux:button>
                        </form>
                    </div>
                @else
                    <div class="flex items-center gap-4">
                        <flux:avatar size="lg" :src="$teamData['logo_url']" :name="$teamData['name']" data-test="team-logo-preview" />

                        <div>
                            <flux:heading>{{ $teamData['name'] }}</flux:heading>

                            @if ($teamData['formatted_address'])
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400" data-test="team-formatted-address">
                                    {{ $teamData['formatted_address'] }}
                                </flux:text>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading>{{ __('Team members') }}</flux:heading>
                        @if ($this->permissions->canAddMember || $this->permissions->canUpdateMember || $this->permissions->canRemoveMember)
                            <flux:subheading>{{ __('Manage who belongs to this team') }}</flux:subheading>
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
                    @foreach ($members as $member)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="member-row">
                            <div class="flex items-center gap-4">
                                <flux:avatar :name="$member['name']" :initials="$member['initials']" />
                                <div>
                                    <div class="font-medium">{{ $member['name'] }}</div>
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $member['email'] }}</flux:text>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($member['role'] !== 'owner' && $this->permissions->canUpdateMember)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="outline" size="sm" icon:trailing="chevron-down" data-test="member-role-trigger">
                                            {{ $member['role_label'] }}
                                        </flux:button>
                                        <flux:menu>
                                            @foreach ($availableRoles as $role)
                                                <flux:menu.item
                                                    as="button"
                                                    type="button"
                                                    wire:click="updateMember({{ $member['id'] }}, '{{ $role['value'] }}')"
                                                    data-test="member-role-option"
                                                >
                                                    {{ $role['label'] }}
                                                </flux:menu.item>
                                            @endforeach
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <flux:badge color="zinc">{{ $member['role_label'] }}</flux:badge>
                                @endif

                                @if ($member['role'] !== 'owner' && $this->permissions->canRemoveMember)
                                    <flux:modal.trigger name="remove-member-{{ $member['id'] }}">
                                        <flux:tooltip :content="__('Remove member')">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="x-mark"
                                                data-test="member-remove-button"
                                            />
                                        </flux:tooltip>
                                    </flux:modal.trigger>
                                @endif
                            </div>
                        </div>

                        @if ($member['role'] !== 'owner' && $this->permissions->canRemoveMember)
                            <livewire:pages::teams.remove-member-modal
                                :team="$teamModel"
                                :member-id="$member['id']"
                                :member-name="$member['name']"
                                :modal-name="'remove-member-'.$member['id']"
                                :key="'remove-member-modal-'.$member['id']"
                            />
                        @endif
                    @endforeach
                </div>
            </div>

            @if (count($invitations) > 0)
                <div class="space-y-6">
                    <div>
                        <flux:heading>{{ __('Pending invitations') }}</flux:heading>
                        <flux:subheading>{{ __('Invitations that have not been accepted yet') }}</flux:subheading>
                    </div>

                    <div class="space-y-3">
                        @foreach ($invitations as $invitation)
                            <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="invitation-row">
                                <div class="flex items-center gap-4">
                                    <div class="flex size-10 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon name="envelope" class="text-zinc-500" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $invitation['email'] }}</div>
                                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $invitation['role_label'] }}</flux:text>
                                    </div>
                                </div>

                                @if ($this->permissions->canCancelInvitation)
                                    <flux:modal.trigger name="cancel-invitation-{{ $invitation['code'] }}">
                                        <flux:tooltip :content="__('Cancel invitation')">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="x-mark"
                                                data-test="invitation-cancel-button"
                                            />
                                        </flux:tooltip>
                                    </flux:modal.trigger>
                                @endif
                            </div>
                            @if ($this->permissions->canCancelInvitation)
                                <livewire:pages::teams.cancel-invitation-modal
                                    :team="$teamModel"
                                    :invitation-code="$invitation['code']"
                                    :invitation-email="$invitation['email']"
                                    :modal-name="'cancel-invitation-'.$invitation['code']"
                                    :key="'cancel-invitation-modal-'.$invitation['code']"
                                />
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($this->permissions->canDeleteTeam && ! $teamData['is_personal'])
                <div class="space-y-6">
                    <div>
                        <flux:heading>{{ __('Delete team') }}</flux:heading>
                        <flux:subheading>{{ __('Permanently delete your team') }}</flux:subheading>
                    </div>

                    <div class="space-y-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-200/10 dark:bg-red-900/20 dark:text-red-100">
                        <div>
                            <p class="font-medium">{{ __('Warning') }}</p>
                            <p class="text-sm">{{ __('Please proceed with caution, this cannot be undone.') }}</p>
                        </div>

                        <flux:modal.trigger name="delete-team">
                            <flux:button variant="danger" data-test="delete-team-button">
                                {{ __('Delete team') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>
            @endif
        </div>
    </x-pages::settings.layout>

    @if ($this->permissions->canCreateInvitation)
        <livewire:pages::teams.invite-member-modal :team="$teamModel" />
    @endif

    @if ($this->permissions->canDeleteTeam && ! $teamData['is_personal'])
        <livewire:pages::teams.delete-team-modal :team="$teamModel" />
    @endif
</section>
