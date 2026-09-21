@use('Illuminate\Support\Js')

@props([
    'catalog' => null,
    'model',
    'options',
    'selected' => null,
    'multiple' => false,
    'index' => null,
    'testId' => null,
    'placeholder' => null,
    'errorKey' => null,
    'create' => null,
    'cacheKey' => null,
    'detailModel' => null,
    'detailPlaceholder' => null,
])

{{--
    The options passed in only name the selected entries, through data-selected-options. That attribute, and
    not x-data, carries them: Livewire re-creates the Alpine component when its x-data expression changes. The full list is loaded by
    the catalogPicker Alpine component (resources/js/catalog-picker.js) the first time the picker opens, and
    choosing an entry does not send a request: the selection, drawn by Alpine, goes with the next save.
--}}
@php
    $testId ??= 'catalog-picker-'.$catalog?->value;
    $placeholder ??= $catalog?->placeholder();
    $create ??= '(name) => $wire.createCatalogEntry('.Js::from($catalog?->value).', name, '.Js::from($index).')';
    $cacheKey ??= $catalog?->value;
    $errorKey ??= 'new_'.$catalog?->value;
    $selectedIds = collect($multiple ? (array) $selected : [$selected])->filter(fn ($id) => $id !== null && $id !== '')->map(fn ($id) => (string) $id);
    $selectedOptions = $selectedIds->map(fn (string $id) => $options->first(fn ($option) => (string) $option->id === $id))->filter()->values();
    $invalid = $errors->has($model) || $errors->has($model.'.*') || $errors->has($errorKey);
@endphp

<div
    x-data="catalogPicker({
        model: {{ Js::from($model) }},
        multiple: {{ Js::from((bool) $multiple) }},
        cacheKey: {{ Js::from($cacheKey) }},
        create: {!! $create !!},
        detailModel: {{ Js::from($detailModel) }},
    })"
    data-selected-options="{{ $selectedOptions->mapWithKeys(fn ($option) => [(string) $option->id => $option->name])->toJson() }}"
    x-on:click.outside="close()"
    x-on:keydown.escape.prevent.stop="close()"
    data-test="{{ $testId }}"
>
    @php($triggerClasses = [
        'flex min-h-10 w-full items-center gap-2 rounded-lg border bg-white py-1.5 ps-3 pe-3 text-start text-base shadow-xs sm:text-sm dark:bg-white/10',
        'border-red-500' => $invalid,
        'border-zinc-200 border-b-zinc-300/80 dark:border-white/10' => ! $invalid,
    ])

    <div class="relative">
        @if ($multiple)
            <div x-on:click="toggle()" @class([...$triggerClasses, 'cursor-pointer']) data-test="{{ $testId }}-trigger">
                <span wire:ignore class="flex min-w-0 flex-wrap gap-1">
                    <span x-show="selectedIds().length === 0" class="truncate text-zinc-400">{{ $placeholder }}</span>

                    <template x-for="id in selectedIds()" :key="id">
                        <flux:badge size="sm" color="zinc">
                            <span x-text="nameOf(id)"></span>
                            <flux:badge.close
                                x-on:click.stop="choose(id)"
                                x-bind:aria-label="{{ Js::from(__('Remove :item')) }}.replace(':item', nameOf(id))"
                                data-test="{{ $testId }}-remove"
                            />
                        </flux:badge>
                    </template>
                </span>

                <button
                    type="button"
                    x-on:click.stop="toggle()"
                    x-bind:aria-expanded="open"
                    aria-label="{{ $placeholder }}"
                    class="ms-auto shrink-0 text-zinc-400"
                >
                    <flux:icon name="chevron-up-down" variant="mini" class="size-4" />
                </button>
            </div>
        @else
            <button
                type="button"
                x-on:click="toggle()"
                x-bind:aria-expanded="open"
                @class($triggerClasses)
                data-test="{{ $testId }}-trigger"
            >
                <span wire:ignore class="min-w-0 truncate">
                    <span x-show="selectedIds().length === 0" class="text-zinc-400">{{ $placeholder }}</span>
                    <span x-show="selectedIds().length > 0" x-text="selectedIds().length > 0 ? nameOf(selectedIds()[0]) : ''" class="text-zinc-700 dark:text-zinc-300"></span>
                </span>

                <flux:icon name="chevron-up-down" variant="mini" class="ms-auto size-4 shrink-0 text-zinc-400" />
            </button>
        @endif

        <div
            wire:ignore
            x-show="open"
            x-cloak
            x-transition.opacity.duration.100ms
            class="absolute inset-x-0 top-full z-20 mt-1 rounded-lg border border-zinc-200 bg-white shadow-lg sm:min-w-64 dark:border-white/10 dark:bg-zinc-800"
        >
            <div class="border-b border-zinc-100 p-2 dark:border-white/5">
                <input
                    type="text"
                    x-ref="search"
                    x-model="search"
                    x-on:input.stop
                    x-on:change.stop
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="confirm()"
                    placeholder="{{ __('Search or create...') }}"
                    autocomplete="off"
                    class="w-full rounded-md border-0 bg-transparent px-2 py-1 text-sm text-zinc-800 outline-none placeholder:text-zinc-400 dark:text-white"
                    data-test="{{ $testId }}-search"
                />
            </div>

            <div x-ref="options" role="listbox" class="max-h-64 overflow-y-auto py-1">
                <div x-show="cache.loading" class="px-3 py-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Loading...') }}
                </div>

                <template x-for="(option, optionIndex) in visible" :key="option.id">
                    <button
                        type="button"
                        role="option"
                        x-bind:aria-selected="isSelected(option.id)"
                        x-bind:data-highlighted="highlighted === optionIndex"
                        x-bind:class="{ 'bg-zinc-50 dark:bg-zinc-700/50': highlighted === optionIndex }"
                        x-on:mouseenter="highlighted = optionIndex"
                        x-on:click="choose(option.id)"
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-start text-sm text-zinc-800 dark:text-white"
                        data-test="{{ $testId }}-option"
                    >
                        <flux:icon name="check" variant="mini" class="size-4 shrink-0" x-bind:class="{ 'invisible': ! isSelected(option.id) }" />
                        <span class="truncate" x-text="option.name"></span>
                    </button>
                </template>

                <div
                    x-show="hiddenCount > 0"
                    x-text="{{ Js::from(__('Showing :shown of :total. Refine your search.')) }}.replace(':shown', visible.length).replace(':total', matches.length)"
                    class="px-3 py-1.5 text-xs text-zinc-500 dark:text-zinc-400"
                ></div>

                <button
                    type="button"
                    x-show="canCreate"
                    x-bind:disabled="creating"
                    x-bind:data-highlighted="highlighted === visible.length"
                    x-bind:class="{ 'bg-zinc-50 dark:bg-zinc-700/50': highlighted === visible.length }"
                    x-on:mouseenter="highlighted = visible.length"
                    x-on:click="create()"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-start text-sm text-zinc-800 disabled:opacity-50 dark:text-white"
                    data-test="{{ $testId }}-create"
                >
                    <flux:icon name="plus" variant="mini" class="size-4 shrink-0" />
                    <span class="truncate">{{ __('Create') }} «<span x-text="search.trim()"></span>»</span>
                </button>

                <div x-show="cache.loaded && cache.options.length === 0 && search.trim() === ''" class="px-3 py-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Type a name to create the first one.') }}
                </div>
            </div>
        </div>
    </div>

    @if ($detailModel)
        <div wire:ignore class="mt-3 space-y-3">
            <template x-for="id in selectedIds()" :key="id">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between" data-test="{{ $testId }}-detail">
                    <flux:text class="text-zinc-800 dark:text-white"><span x-text="nameOf(id)"></span></flux:text>
                    <div class="sm:w-48">
                        <flux:input
                            size="sm"
                            maxlength="100"
                            :placeholder="$detailPlaceholder"
                            :aria-label="__('Detail')"
                            x-bind:value="detailOf(id)"
                            x-on:input="setDetail(id, $event.target.value)"
                            data-test="{{ $testId }}-detail-input"
                        />
                    </div>
                </div>
            </template>
        </div>
    @endif

    <flux:error :name="$errorKey" />
</div>
