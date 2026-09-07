<?php

use App\Livewire\Forms\AssistedPersonForm;
use App\Models\AssistedPerson;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit assisted person')] class extends Component {
    public AssistedPersonForm $form;

    public AssistedPerson $assistedPerson;

    public function mount(AssistedPerson $assistedPerson): void
    {
        Gate::authorize('update', $assistedPerson);

        $this->assistedPerson = $assistedPerson;

        $this->form->setAssistedPerson($assistedPerson);
    }

    public function save(): void
    {
        Gate::authorize('update', $this->assistedPerson);

        $this->form->update($this->assistedPerson);

        Flux::toast(variant: 'success', text: __('Assisted person updated.'));

        $this->redirectRoute('assisted-people.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-3xl">
    <div>
        <flux:heading size="xl">{{ __('Edit assisted person') }}</flux:heading>
        <flux:subheading>{{ $assistedPerson->name }}</flux:subheading>
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
