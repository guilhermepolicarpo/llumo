@props(['appointmentTypes', 'modes', 'selectedAssistedPerson', 'assistedPersonSearch', 'assistedPersonSuggestions', 'searchMinLength'])

<flux:fieldset>
    <div class="grid gap-6 sm:grid-cols-2">
        <flux:field>
            <flux:label>{{ __('Appointment type') }}</flux:label>

            <div class="flex items-start gap-2">
                <flux:select wire:model="appointmentTypeId" :placeholder="__('Select a type...')" class="flex-1" data-test="appointment-type-select" required>
                    @foreach ($appointmentTypes as $appointmentType)
                        <flux:select.option :value="$appointmentType->id" wire:key="appointment-type-{{ $appointmentType->id }}">{{ $appointmentType->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:tooltip :content="__('New appointment type')">
                    <flux:button
                        icon="plus"
                        x-on:click="$flux.modal('create-appointment-type').show()"
                        :aria-label="__('New appointment type')"
                        data-test="appointment-new-type-button"
                    />
                </flux:tooltip>
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

                @if (mb_strlen(trim($assistedPersonSearch)) >= $searchMinLength)
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
