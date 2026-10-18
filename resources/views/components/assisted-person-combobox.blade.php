{{--
    Search the team's assisted people by name and select one, bound to the InteractsWithAssistedPersonPicker
    properties of the component. "Register" opens the create-assisted-person modal, which the page includes.
--}}
@props(['selected', 'search', 'suggestions', 'testPrefix'])

<flux:field {{ $attributes }}>
    <flux:label>{{ __('Assisted person') }}</flux:label>

    @if ($selected)
        <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-white/10" data-test="{{ $testPrefix }}-selected-assisted-person">
            <div class="min-w-0">
                <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $selected->name }}</div>
                @foreach (array_filter([$selected->formatted_age, $selected->contact, $selected->address_summary]) as $detail)
                    <div class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $detail }}</div>
                @endforeach
            </div>

            <flux:button
                size="sm"
                variant="ghost"
                icon="arrows-right-left"
                x-on:click="$wire.clearAssistedPerson().then(() => $nextTick(() => document.querySelector('[data-test={{ $testPrefix }}-assisted-person-search-input]')?.focus()))"
                data-test="{{ $testPrefix }}-change-assisted-person-button"
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
                        this.$refs.suggestions?.querySelector('[data-test={{ $testPrefix }}-register-searched-assisted-person-button]')?.click();

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
                aria-controls="{{ $testPrefix }}-assisted-person-suggestions"
                x-bind:aria-expanded="open"
                x-on:focus="open = true"
                x-on:input="open = true; highlighted = -1"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="choose()"
                data-test="{{ $testPrefix }}-assisted-person-search-input"
            />

            @if ($suggestions !== null)
                <div
                    id="{{ $testPrefix }}-assisted-person-suggestions"
                    role="listbox"
                    x-ref="suggestions"
                    x-show="open"
                    class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-white/10 dark:bg-zinc-800"
                    data-test="{{ $testPrefix }}-assisted-person-suggestions"
                >
                    @forelse ($suggestions as $suggestion)
                        <button
                            type="button"
                            role="option"
                            tabindex="-1"
                            wire:key="assisted-person-suggestion-{{ $suggestion->id }}"
                            wire:click="selectAssistedPerson({{ $suggestion->id }})"
                            x-on:mouseenter="highlighted = {{ $loop->index }}"
                            x-bind:aria-selected="highlighted === {{ $loop->index }}"
                            x-bind:class="{ 'bg-zinc-50 dark:bg-zinc-700/50': highlighted === {{ $loop->index }} }"
                            class="flex w-full flex-col items-start px-3 py-2 text-start not-last:border-b not-last:border-zinc-100 dark:not-last:border-white/5"
                            data-test="{{ $testPrefix }}-assisted-person-suggestion"
                        >
                            <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $suggestion->name }}</span>
                            @php($suggestionDetails = collect([$suggestion->formatted_age, $suggestion->contact])->filter()->implode(' · '))
                            @if ($suggestionDetails !== '')
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $suggestionDetails }}</span>
                            @endif
                            @if ($suggestion->address_summary)
                                <span class="w-full truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $suggestion->address_summary }}</span>
                            @endif
                        </button>
                    @empty
                        <div class="flex flex-col items-start gap-2 px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <flux:text>{{ __('No assisted people match your search.') }}</flux:text>

                            <flux:button
                                size="sm"
                                icon="plus"
                                x-on:click="$dispatch('open-create-assisted-person', { name: $wire.assistedPersonSearch })"
                                data-test="{{ $testPrefix }}-register-searched-assisted-person-button"
                            >
                                {{ __('Register ":name"', ['name' => trim($search)]) }}
                            </flux:button>
                        </div>
                    @endforelse
                </div>
            @endif
        </div>
    @endif

    <flux:error name="assistedPersonId" />
</flux:field>
