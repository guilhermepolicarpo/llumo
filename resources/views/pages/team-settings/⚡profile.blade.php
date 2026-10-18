<?php

use App\Actions\Teams\UpdateTeamProfile;
use App\Concerns\InteractsWithAddressForm;
use App\Models\Team;
use App\Rules\Phone;
use App\Rules\PostalCode;
use App\Rules\TeamProfileRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use InteractsWithAddressForm, WithFileUploads;

    public Team $team;

    public string $teamName = '';

    public string $legalName = '';

    public string $phone = '';

    public ?TemporaryUploadedFile $logo = null;

    public function mount(): void
    {
        $team = Auth::user()->currentTeam;

        Gate::authorize('update', $team);

        $this->team = $team;
        $this->teamName = $team->name;
        $this->legalName = $team->legal_name ?? '';
        $this->phone = Phone::format($team->phone);

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
                'legal_name' => $validated['legalName'] ?? null,
                'phone' => $validated['phone'] ?? null,
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
            $this->redirectRoute('team-settings.profile', ['current_team' => $this->team->slug], navigate: true);
        }
    }

    public function removeLogo(UpdateTeamProfile $updateTeamProfile): void
    {
        Gate::authorize('update', $this->team);

        $this->team = $updateTeamProfile->removeLogo($this->team);

        $this->reset('logo');

        Flux::toast(variant: 'success', text: __('Logo removed.'));
    }

    #[Computed]
    public function canDelete(): bool
    {
        return Gate::allows('delete', $this->team);
    }

    public function render()
    {
        return $this->view()->title(__('Center details'));
    }
}; ?>

<section class="w-full">
    @include('partials.team-settings-heading')

    <x-pages::team-settings.layout :heading="__('Center details')" :subheading="__('Manage your Spiritist Center logo, names, phone, and address')">
        <div class="space-y-10">
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
                                    size="sm"
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

                    <div class="mt-6 grid gap-6 sm:grid-cols-2">
                        <flux:input wire:model="teamName" :label="__('Name')" :description="__('Short name shown in the sidebar.')" required data-test="team-name-input" />

                        <flux:input wire:model="legalName" :label="__('Full name')" :description="__('Printed on the attendance sheets and reports.')" data-test="team-legal-name-input" />

                        <flux:input
                            wire:model="phone"
                            :label="__('Phone')"
                            placeholder="(00) 00000-0000"
                            inputmode="numeric"
                            mask:dynamic="$input.replace(/\D/g, '').length > 10 ? '(99) 99999-9999' : '(99) 9999-99999'"
                            data-test="team-phone-input"
                        />
                    </div>
                </flux:fieldset>

                <x-pages::address-form :states="$this->states" test-prefix="team" />

                <flux:button variant="primary" type="submit" data-test="team-save-button">
                    {{ __('Save changes') }}
                </flux:button>
            </form>

            @if ($this->canDelete)
                <div class="space-y-6">
                    <flux:separator />

                    <div>
                        <flux:heading>{{ __('Delete Spiritist Center') }}</flux:heading>
                        <flux:subheading>{{ __('Deletes the Spiritist Center and all of its data. This cannot be undone.') }}</flux:subheading>
                    </div>

                    <flux:modal.trigger name="delete-team">
                        <flux:button variant="danger" data-test="delete-team-button">
                            {{ __('Delete Spiritist Center') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif
        </div>
    </x-pages::team-settings.layout>

    @if ($this->canDelete)
        <livewire:pages::teams.delete-team-modal :team="$team" />
    @endif
</section>
