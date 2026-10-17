{{--
    Picks a single day to browse a list by, bound with wire:model to a 'Y-m-d' string: arrows step one day, the
    date opens the shared month calendar, and with allow-all the day can be cleared (an empty string) to list every
    date. markers names a renderless method of the Livewire component that, given a month ('Y-m'), returns the days
    to mark with a dot. The behaviour lives in the dayPicker Alpine component (resources/js/day-picker.js).
--}}
@props([
    'allowAll' => false,
    'markers' => null,
    'markerLabel' => null,
])

<div
    x-data="dayPicker({
        today: @js(today()->toDateString()),
        locale: @js(str_replace('_', '-', app()->getLocale())),
    })"
    x-modelable="value"
    {{ $attributes->whereStartsWith('wire:model') }}
    {{ $attributes->whereDoesntStartWith('wire:model')->class('flex flex-wrap items-center gap-3') }}
>
    <flux:button.group>
        <flux:tooltip position="bottom">
            <flux:button
                square
                icon="chevron-left"
                icon:variant="outline"
                x-on:click="shift(-1)"
                x-bind:disabled="all"
                :aria-label="__('Previous day')"
                data-test="day-picker-previous"
            />

            <flux:tooltip.content><span x-text="neighbourLabel(-1)"></span></flux:tooltip.content>
        </flux:tooltip>

        <flux:dropdown position="bottom" align="start">
            <flux:button
                icon="calendar-days"
                icon:variant="outline"
                x-ref="trigger"
                class="aria-expanded:bg-blue-50! aria-expanded:text-blue-700! dark:aria-expanded:bg-blue-500/15! dark:aria-expanded:text-blue-300!"
                :aria-label="__('Choose date')"
                data-test="day-picker-trigger"
            >
                <span x-show="all" class="font-normal text-zinc-500 dark:text-zinc-400">{{ __('Choose date') }}</span>

                <span x-show="! all" class="flex items-center gap-1.5 whitespace-nowrap">
                    <span class="max-sm:hidden" x-text="weekdayLong"></span>
                    <span class="sm:hidden" x-text="weekdayShort"></span>
                    <span class="font-normal text-zinc-500 max-sm:hidden dark:text-zinc-400" x-text="dateLong"></span>
                    <span class="font-normal text-zinc-500 sm:hidden dark:text-zinc-400" x-text="dateShort"></span>
                </span>
            </flux:button>

            <x-calendar :markers="$markers" :marker-label="$markerLabel" data-test="day-picker-calendar" />
        </flux:dropdown>

        <flux:tooltip position="bottom">
            <flux:button
                square
                icon="chevron-right"
                icon:variant="outline"
                x-on:click="shift(1)"
                x-bind:disabled="all"
                :aria-label="__('Next day')"
                data-test="day-picker-next"
            />

            <flux:tooltip.content><span x-text="neighbourLabel(1)"></span></flux:tooltip.content>
        </flux:tooltip>
    </flux:button.group>

    <flux:badge size="sm" color="blue" x-show="isToday" data-test="day-picker-today-badge">{{ __('Today') }}</flux:badge>

    <flux:button size="sm" x-show="! all && ! isToday" x-on:click="value = today" data-test="day-picker-go-to-today">{{ __('Go to today') }}</flux:button>

    @if ($allowAll)
        <flux:button
            size="sm"
            variant="ghost"
            x-on:click="toggleAll()"
            x-bind:aria-pressed="all"
            class="aria-pressed:bg-blue-50! aria-pressed:text-blue-700! dark:aria-pressed:bg-blue-500/15! dark:aria-pressed:text-blue-300!"
            data-test="day-picker-all-dates"
        >
            {{ __('All dates') }}
        </flux:button>
    @endif
</div>
