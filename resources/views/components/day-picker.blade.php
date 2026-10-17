{{--
    Picks a single day, bound with wire:model to a 'Y-m-d' string: arrows step one day, the date opens a month
    calendar, and with allow-all the day can be cleared (an empty string) to list every date. markers names a
    renderless method of the Livewire component that, given a month ('Y-m'), returns the days to mark with a dot.
    The behaviour lives in the dayPicker Alpine component (resources/js/day-picker.js).
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
        markers: @js($markers),
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

            <div
                popover="manual"
                role="dialog"
                aria-label="{{ __('Choose date') }}"
                x-ref="panel"
                x-on:toggle="toggled($event)"
                x-on:keydown.escape.prevent.stop="close()"
                class="w-80 flex-col gap-3 rounded-xl open:flex border border-zinc-200 bg-white p-4 shadow-lg dark:border-zinc-600 dark:bg-zinc-700"
                data-test="day-picker-calendar"
            >
                <div class="flex items-center gap-1">
                    <span class="grow ps-1 text-sm font-semibold text-zinc-800 dark:text-white" x-text="monthLabel"></span>

                    <flux:button size="sm" variant="ghost" square icon="chevron-left" icon:variant="outline" x-on:click="showMonth(-1)" :aria-label="__('Previous month')" />
                    <flux:button size="sm" variant="ghost" square icon="chevron-right" icon:variant="outline" x-on:click="showMonth(1)" :aria-label="__('Next month')" />
                </div>

                <div class="grid grid-cols-7 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400" aria-hidden="true">
                    <template x-for="(weekday, index) in weekdays" :key="index">
                        <span x-text="weekday"></span>
                    </template>
                </div>

                <div class="grid grid-cols-7 gap-0.5" x-on:keydown="navigate($event)">
                    <template x-for="day in days" :key="day.iso">
                        <button
                            type="button"
                            x-on:click="select(day.iso)"
                            x-bind:data-day="day.iso"
                            x-bind:tabindex="day.iso === focused ? 0 : -1"
                            x-bind:aria-label="day.label"
                            x-bind:aria-pressed="day.iso === value"
                            x-bind:aria-current="day.iso === today ? 'date' : null"
                            x-bind:class="day.inMonth ? 'text-zinc-800 dark:text-white' : 'text-zinc-400 dark:text-zinc-500'"
                            class="group flex h-9.5 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-lg text-sm not-aria-pressed:hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-accent aria-[current=date]:font-semibold aria-[current=date]:not-aria-pressed:text-accent-content aria-pressed:bg-accent aria-pressed:font-semibold aria-pressed:text-accent-foreground dark:not-aria-pressed:hover:bg-white/10"
                            data-test="day-picker-day"
                        >
                            <span x-text="day.number"></span>
                            <span class="size-1 rounded-full" x-bind:class="day.marked && 'bg-accent-content group-aria-pressed:bg-accent-foreground'"></span>
                        </button>
                    </template>
                </div>

                <div class="flex items-center gap-2 border-t border-zinc-100 pt-3 dark:border-white/10">
                    @if ($markers && $markerLabel)
                        <span class="flex grow items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="size-1.5 rounded-full bg-accent-content"></span>
                            {{ $markerLabel }}
                        </span>
                    @endif

                    <flux:button size="sm" class="ms-auto" x-on:click="select(today)" data-test="day-picker-calendar-today">{{ __('Today') }}</flux:button>
                </div>
            </div>
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
