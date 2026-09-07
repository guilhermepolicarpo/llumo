<?php

use App\Livewire\Forms\AssistedPersonForm;
use App\Models\AssistedPerson;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New assisted person')] class extends Component {
    public AssistedPersonForm $form;

    public function mount(): void
    {
        Gate::authorize('create', [AssistedPerson::class, $this->team]);
    }

    public function save(): void
    {
        Gate::authorize('create', [AssistedPerson::class, $this->team]);

        $this->form->store($this->team);

        Flux::toast(variant: 'success', text: __('Assisted person created.'));

        $this->redirectRoute('assisted-people.index', navigate: true);
    }

    #[Computed]
    public function team(): Team
    {
        return Auth::user()->currentTeam;
    }
}; ?>

<section class="w-full max-w-3xl">
    <div>
        <flux:heading size="xl">{{ __('New assisted person') }}</flux:heading>
        <flux:subheading>{{ __('Register a person assisted by this house') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="mt-6 space-y-6">
        <x-pages::assisted-people.form-fields />

        <div class="flex items-center gap-2">
            <flux:button variant="primary" type="submit" data-test="assisted-person-save-button">
                {{ __('Save') }}
            </flux:button>

            <flux:button variant="ghost" :href="route('assisted-people.index')" wire:navigate data-test="assisted-person-cancel-button">
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>
</section>
