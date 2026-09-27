<?php

use App\Actions\AssistedPeople\CreateAssistedPerson;
use App\Concerns\InteractsWithAddressForm;
use App\Concerns\InteractsWithAssistedPersonForm;
use App\Models\AssistedPerson;
use App\Rules\AssistedPersonRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    use InteractsWithAddressForm, InteractsWithAssistedPersonForm;

    public function mount(): void
    {
        Gate::authorize('create', [AssistedPerson::class, Auth::user()->currentTeam]);
    }

    public function createAssistedPerson(CreateAssistedPerson $createAssistedPerson): void
    {
        Gate::authorize('create', [AssistedPerson::class, Auth::user()->currentTeam]);

        $validated = $this->validate(AssistedPersonRules::all());

        $createAssistedPerson->handle(Auth::user()->currentTeam, $this->assistedPersonAttributes($validated));

        Flux::toast(variant: 'success', text: __('Assisted person created.'));

        $this->redirectRoute('assisted-people.index', navigate: true);
    }

    public function render()
    {
        return $this->view()->title(__('New assisted person'));
    }
}; ?>

<section class="w-full">
    <x-page-header :heading="__('New assisted person')" :subheading="__('Register a new assisted person for this Spiritist Center')" :back-href="route('assisted-people.index')" :back-label="__('Back to assisted people')" />

    <form wire:submit="createAssistedPerson" class="max-w-xl space-y-6">
        <x-assisted-people.fields />

        <x-pages::address-form :states="$this->states" test-prefix="assisted-person" />

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('assisted-people.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>

            <flux:button variant="primary" type="submit" data-test="assisted-person-save-button">
                {{ __('Register assisted person') }}
            </flux:button>
        </div>
    </form>
</section>
