@props(['appointmentTypes', 'modes', 'selectedAssistedPerson', 'assistedPersonSearch', 'assistedPersonSuggestions'])

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
                            class="cursor-pointer"
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

        <flux:input type="date" wire:model="scheduledOn" :label="__('Date')" required data-test="appointment-scheduled-on-input" />
    </div>

    <div class="mt-6">
        <flux:radio.group wire:model="mode" :label="__('Mode')" variant="segmented" data-test="appointment-mode-radio">
            @foreach ($modes as $modeOption)
                <flux:radio :value="$modeOption['value']" :label="$modeOption['label']" />
            @endforeach
        </flux:radio.group>
    </div>

    <flux:field class="mt-6">
        <flux:label>{{ __('Assisted person') }}</flux:label>

        @if ($selectedAssistedPerson)
            <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-white/10" data-test="appointment-selected-assisted-person">
                <div class="min-w-0">
                    <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $selectedAssistedPerson->name }}</div>
                    @if ($selectedAssistedPerson->contact)
                        <div class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $selectedAssistedPerson->contact }}</div>
                    @endif
                </div>

                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="arrows-right-left"
                    x-on:click="$wire.clearAssistedPerson().then(() => $nextTick(() => document.querySelector('[data-test=appointment-assisted-person-search-input]')?.focus()))"
                    data-test="appointment-change-assisted-person-button"
                >
                    {{ __('Change') }}
                </flux:button>
            </div>
        @else
            <div
                class="relative"
                x-data="{
                    open: true,
                    highlighted: -1,
                    options() {
                        return this.$refs.suggestions?.querySelectorAll('[role=option]') ?? [];
                    },
                    move(step) {
                        this.open = true;
                        const options = this.options();

                        if (options.length === 0) {
                            return;
                        }

                        this.highlighted = (this.highlighted + step + options.length) % options.length;
                        options[this.highlighted].scrollIntoView({ block: 'nearest' });
                    },
                    choose() {
                        const options = this.options();

                        if (options.length === 0) {
                            this.$refs.suggestions?.querySelector('[data-test=appointment-register-searched-assisted-person-button]')?.click();

                            return;
                        }

                        options[this.highlighted]?.click();
                    },
                }"
                x-on:click.outside="open = false"
                x-on:keydown.escape="open = false"
                x-on:keydown.tab="open = false"
            >
                <flux:input
                    wire:model.live.debounce.300ms="assistedPersonSearch"
                    icon="magnifying-glass"
                    :placeholder="__('Search by name...')"
                    autocomplete="off"
                    role="combobox"
                    aria-controls="appointment-assisted-person-suggestions"
                    x-bind:aria-expanded="open"
                    x-on:focus="open = true"
                    x-on:input="open = true; highlighted = -1"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="choose()"
                    data-test="appointment-assisted-person-search-input"
                />

                @if ($assistedPersonSuggestions !== null)
                    <div
                        id="appointment-assisted-person-suggestions"
                        role="listbox"
                        x-ref="suggestions"
                        x-show="open"
                        class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-white/10 dark:bg-zinc-800"
                        data-test="appointment-assisted-person-suggestions"
                    >
                        @forelse ($assistedPersonSuggestions as $suggestion)
                            <button
                                type="button"
                                role="option"
                                tabindex="-1"
                                wire:key="assisted-person-suggestion-{{ $suggestion->id }}"
                                wire:click="selectAssistedPerson({{ $suggestion->id }})"
                                x-on:mouseenter="highlighted = {{ $loop->index }}"
                                x-bind:aria-selected="highlighted === {{ $loop->index }}"
                                x-bind:class="{ 'bg-zinc-50 dark:bg-zinc-700/50': highlighted === {{ $loop->index }} }"
                                class="flex w-full cursor-pointer flex-col items-start px-3 py-2 text-start not-last:border-b not-last:border-zinc-100 dark:not-last:border-white/5"
                                data-test="appointment-assisted-person-suggestion"
                            >
                                <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $suggestion->name }}</span>
                                @if ($suggestion->contact)
                                    <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $suggestion->contact }}</span>
                                @endif
                            </button>
                        @empty
                            <div class="flex flex-col items-start gap-2 px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <flux:text>{{ __('No assisted people match your search.') }}</flux:text>

                                <flux:button
                                    size="sm"
                                    icon="plus"
                                    x-on:click="$dispatch('open-quick-create-assisted-person', { name: $wire.assistedPersonSearch })"
                                    data-test="appointment-register-searched-assisted-person-button"
                                >
                                    {{ __('Register ":name"', ['name' => trim($assistedPersonSearch)]) }}
                                </flux:button>
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>
        @endif

        <flux:error name="assistedPersonId" />
    </flux:field>

    <div class="mt-6">
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="4" data-test="appointment-notes-input" />
    </div>
</flux:fieldset>
