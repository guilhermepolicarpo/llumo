@props(['appointmentTypes', 'weekdayRestrictedTypes', 'modes', 'selectedAssistedPerson', 'assistedPersonSearch', 'assistedPersonSuggestions'])

<flux:fieldset>
    <div class="grid gap-6 sm:grid-cols-2">
        <flux:field>
            <flux:label>{{ __('Appointment type') }}</flux:label>

            <div x-data="{ names: @js($appointmentTypes->mapWithKeys(fn ($appointmentType) => [(string) $appointmentType->id => $appointmentType->name])) }">
                <flux:dropdown position="bottom" align="start" class="w-full">
                    <button
                        type="button"
                        @class([
                            'flex h-10 w-full items-center rounded-lg border bg-white ps-3 pe-3 text-start text-base shadow-xs sm:text-sm dark:bg-white/10',
                            'border-red-500' => $errors->has('appointmentTypeId'),
                            'border-zinc-200 border-b-zinc-300/80 dark:border-white/10' => ! $errors->has('appointmentTypeId'),
                        ])
                        data-picker-trigger
                        data-test="appointment-type-select"
                    >
                        <span
                            class="truncate"
                            x-bind:class="names[$wire.appointmentTypeId] ? 'text-zinc-700 dark:text-zinc-300' : 'text-zinc-400'"
                            x-text="names[$wire.appointmentTypeId] ?? @js(__('Select a type...'))"
                        ></span>
                        <flux:icon name="chevron-up-down" variant="mini" class="ms-auto size-4 text-zinc-400" />
                    </button>

                    <flux:menu class="min-w-(--button-width) sm:min-w-64">
                        <flux:menu.radio.group wire:model="appointmentTypeId">
                            @foreach ($appointmentTypes as $appointmentType)
                                <flux:menu.radio :value="(string) $appointmentType->id" wire:key="appointment-type-{{ $appointmentType->id }}">{{ $appointmentType->name }}</flux:menu.radio>
                            @endforeach
                        </flux:menu.radio.group>

                        @if ($appointmentTypes->isNotEmpty())
                            <flux:menu.separator />
                        @endif

                        <flux:menu.item
                            icon="plus"
                            x-on:click="$flux.modal('create-appointment-type').show()"
                            data-test="appointment-new-type-button"
                        >
                            {{ __('New appointment type') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>

            <flux:error name="appointmentTypeId" />
        </flux:field>

        {{-- Checked in the browser so picking the type or the date costs no request; it only warns, the date can still be saved. --}}
        <div
            class="space-y-2"
            x-data="{
                restrictedTypes: @js($weekdayRestrictedTypes),
                get restrictedType() {
                    return this.restrictedTypes[$wire.appointmentTypeId] ?? null;
                },
                get isOffered() {
                    if (! this.restrictedType || ! /^\d{4}-\d{2}-\d{2}$/.test($wire.scheduledOn)) {
                        return true;
                    }

                    const [year, month, day] = $wire.scheduledOn.split('-').map(Number);

                    return this.restrictedType.weekdays.includes(new Date(year, month - 1, day).getDay() || 7);
                },
            }"
        >
            <x-date-input wire:model="scheduledOn" :label="__('Date')" :min="today()->toDateString()" required data-test="appointment-scheduled-on-input" />

            <template x-if="restrictedType && isOffered">
                <flux:text class="text-sm" x-text="restrictedType.hint" data-test="appointment-scheduled-on-weekdays-hint" />
            </template>

            <template x-if="restrictedType && ! isOffered">
                <flux:callout variant="warning" icon="exclamation-triangle" icon:variant="outline" data-test="appointment-scheduled-on-not-offered">
                    <flux:callout.heading x-text="restrictedType.warning" />
                </flux:callout>
            </template>
        </div>
    </div>

    <div class="mt-6">
        <flux:radio.group wire:model="mode" :label="__('Mode')" variant="segmented" data-test="appointment-mode-radio">
            @foreach ($modes as $modeOption)
                <flux:radio :value="$modeOption['value']" :label="$modeOption['label']" />
            @endforeach
        </flux:radio.group>
    </div>

    <x-assisted-person-combobox
        class="mt-6"
        :selected="$selectedAssistedPerson"
        :search="$assistedPersonSearch"
        :suggestions="$assistedPersonSuggestions"
        test-prefix="appointment"
    />

    <div class="mt-6">
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="auto" data-test="appointment-notes-input" />
    </div>
</flux:fieldset>
