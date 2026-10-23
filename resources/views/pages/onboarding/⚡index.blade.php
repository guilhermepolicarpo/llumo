<?php

use App\Actions\Teams\CreateTeam;
use App\Models\Team;
use App\Rules\TeamProfileRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Welcome')] class extends Component {
    public string $name = '';

    public function mount(): void
    {
        if ($team = Auth::user()->resolveCurrentTeam()) {
            $this->redirectRoute('dashboard', ['current_team' => $team->slug], navigate: true);
        }
    }

    public function createTeam(CreateTeam $createTeam): void
    {
        Gate::authorize('create', Team::class);

        $validated = $this->validate([
            'name' => TeamProfileRules::name(),
        ]);

        $team = $createTeam->handle(Auth::user(), $validated['name']);

        Flux::toast(variant: 'success', text: __('Spiritist Center created.'));

        $this->redirectRoute('team-settings.profile', ['current_team' => $team->slug], navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <livewire:pages::teams.pending-invitations-modal />

    @can('create', App\Models\Team::class)
        <div class="text-center">
            <flux:heading size="xl" level="1">{{ __('Create your Spiritist Center') }}</flux:heading>
            <flux:subheading>{{ __('Give your Spiritist Center a name to get started. You can fill in the other details later.') }}</flux:subheading>
        </div>

        <form wire:submit="createTeam" class="flex flex-col gap-6">
            <flux:input wire:model="name" :label="__('Spiritist Center name')" type="text" required autofocus data-test="onboarding-team-name" />

            <flux:button variant="primary" type="submit" class="w-full" data-test="onboarding-create-team-button">
                {{ __('Create Spiritist Center') }}
            </flux:button>
        </form>
    @else
        <div class="text-center">
            <flux:heading size="xl" level="1">{{ __('Welcome') }}</flux:heading>
            <flux:subheading data-test="onboarding-no-team-message">
                {{ __('You are not part of any Spiritist Center yet. Ask an administrator of your Spiritist Center for an invitation.') }}
            </flux:subheading>
        </div>
    @endcan

    <form method="POST" action="{{ route('logout') }}" class="flex justify-center">
        @csrf
        <flux:button variant="ghost" type="submit" class="text-sm" data-test="logout-button">
            {{ __('Log out') }}
        </flux:button>
    </form>

    <livewire:pages::settings.delete-user-form />
</div>
