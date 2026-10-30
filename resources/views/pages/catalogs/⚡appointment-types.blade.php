<?php

use App\Actions\AppointmentTypes\CreateAppointmentType;
use App\Actions\AppointmentTypes\UpdateAppointmentType;
use App\Livewire\Forms\AppointmentTypeForm;
use App\Models\AppointmentType;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public AppointmentTypeForm $form;

    #[Locked]
    public ?int $deletingId = null;

    public string $deletingName = '';

    public function mount(): void
    {
        Gate::authorize('manage', [AppointmentType::class, $this->team()]);
    }

    /**
     * Clear the form once the flyout closes, so it opens empty for a new appointment type.
     * Skips the re-render when the form was already empty, e.g. after a save or an untouched "New".
     */
    public function closeAppointmentType(): void
    {
        $previousValues = $this->form->all();
        $hadErrors = $this->getErrorBag()->isNotEmpty();

        $this->form->reset();
        $this->resetValidation();

        if (! $hadErrors && $this->form->all() === $previousValues) {
            $this->skipRender();
        }
    }

    public function editAppointmentType(int $appointmentTypeId): void
    {
        $appointmentType = $this->findAppointmentType($appointmentTypeId);

        Gate::authorize('update', $appointmentType);

        $this->form->setAppointmentType($appointmentType);
        $this->resetValidation();

        Flux::modal('appointment-type')->show();
    }

    public function saveAppointmentType(CreateAppointmentType $createAppointmentType, UpdateAppointmentType $updateAppointmentType): void
    {
        $team = $this->team();
        $appointmentType = $this->form->editingId !== null ? $this->findAppointmentType($this->form->editingId) : null;

        if ($appointmentType !== null) {
            Gate::authorize('update', $appointmentType);
        } else {
            Gate::authorize('manage', [AppointmentType::class, $team]);
        }

        $attributes = $this->form->validatedAttributes($team, $appointmentType);

        if ($appointmentType !== null) {
            $updateAppointmentType->handle($appointmentType, $attributes);
            $message = __('Appointment type updated.');
        } else {
            $createAppointmentType->handle($team, $attributes);
            $message = __('Appointment type created.');
        }

        $this->closeAppointmentType();

        Flux::modal('appointment-type')->close();

        Flux::toast(variant: 'success', text: $message);
    }

    public function confirmDeleteAppointmentType(int $appointmentTypeId): void
    {
        $appointmentType = $this->findAppointmentType($appointmentTypeId);

        Gate::authorize('delete', $appointmentType);

        $this->deletingId = $appointmentType->id;
        $this->deletingName = $appointmentType->name;

        Flux::modal('delete-appointment-type')->show();
    }

    public function deleteAppointmentType(): void
    {
        $appointmentType = $this->findAppointmentType($this->deletingId);

        Gate::authorize('delete', $appointmentType);

        $appointmentType->delete();

        $this->reset('deletingId', 'deletingName');

        Flux::modal('delete-appointment-type')->close();

        Flux::toast(variant: 'success', text: __('Appointment type deleted.'));
    }

    /**
     * @return Collection<int, AppointmentType>
     */
    #[Computed]
    public function appointmentTypes(): Collection
    {
        return $this->team()->appointmentTypes()->orderBy('name')->get();
    }

    /**
     * Find one of the current team's appointment types, with the team already set for the policy checks.
     */
    private function findAppointmentType(?int $appointmentTypeId): AppointmentType
    {
        $team = $this->team();

        return $team->appointmentTypes()->findOrFail($appointmentTypeId)->setRelation('team', $team);
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    public function render()
    {
        return $this->view()->title(__('Appointment types'));
    }
}; ?>

<section class="w-full">
    @include('partials.team-settings-heading')

    <x-pages::team-settings.layout :heading="__('Appointment types')" :subheading="__('Types of appointment offered by this Spiritist Center.')">
        <x-slot:actions>
            <flux:modal.trigger name="appointment-type">
                <flux:button variant="primary" icon="plus" class="shrink-0" data-test="appointment-type-new-button">
                    {{ __('New appointment type') }}
                </flux:button>
            </flux:modal.trigger>
        </x-slot:actions>

        <flux:card class="[--flux-card-padding:1rem]">
            @if ($this->appointmentTypes->isNotEmpty())
                <flux:table bleed container:class="overflow-hidden">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Days') }}</flux:table.column>
                        <flux:table.column>{{ __('Daily limit') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->appointmentTypes as $appointmentType)
                            <flux:table.row :key="$appointmentType->id" class="relative cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800" data-test="appointment-type-row">
                                <flux:table.cell>
                                    <button
                                        type="button"
                                        wire:click="editAppointmentType({{ $appointmentType->id }})"
                                        class="absolute inset-0 cursor-pointer"
                                        aria-label="{{ __('Edit :name', ['name' => $appointmentType->name]) }}"
                                        data-test="appointment-type-edit-row"
                                    ></button>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-zinc-800 dark:text-white">{{ $appointmentType->name }}</span>
                                        @if ($appointmentType->requires_record)
                                            <flux:badge size="sm" color="blue">{{ __('With record') }}</flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell data-test="appointment-type-weekdays">{{ $appointmentType->weekdaysShortLabel() }}</flux:table.cell>

                                <flux:table.cell>{{ $appointmentType->daily_limit ?? __('No limit') }}</flux:table.cell>

                                <flux:table.cell align="end" class="relative z-10">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" icon:variant="outline" :aria-label="__('More actions')" data-test="appointment-type-actions-trigger" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil" icon:variant="outline" wire:click="editAppointmentType({{ $appointmentType->id }})" data-test="appointment-type-edit-menu-item">
                                                {{ __('Edit') }}
                                            </flux:menu.item>
                                            <flux:menu.item variant="danger" icon="trash" icon:variant="outline" wire:click="confirmDeleteAppointmentType({{ $appointmentType->id }})" data-test="appointment-type-delete-menu-item">
                                                {{ __('Delete') }}
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:text class="pt-8 pb-4 text-center text-zinc-500 dark:text-zinc-400">
                    {{ __('No entries have been registered yet.') }}
                </flux:text>
            @endif
        </flux:card>
    </x-pages::team-settings.layout>

    <flux:modal name="appointment-type" flyout variant="floating" :show="$errors->isNotEmpty()" class="md:w-lg" @close="closeAppointmentType">
        <form wire:submit="saveAppointmentType" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $form->editingId !== null ? __('Edit appointment type') : __('New appointment type') }}</flux:heading>
                <flux:subheading>{{ __('Types of appointment offered by this Spiritist Center.') }}</flux:subheading>
            </div>

            <x-appointment-types.fields />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" data-test="appointment-type-save-button">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-appointment-type" focusable class="max-w-lg">
        <form wire:submit="deleteAppointmentType" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete appointment type') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete :name? It will no longer be offered in new appointments, but will remain on the records that already use it.', ['name' => $deletingName]) }}
                </flux:subheading>
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit" wire:loading.attr="disabled" data-test="appointment-type-delete-confirm">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
