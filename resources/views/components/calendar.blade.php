{{--
    The month calendar shared by the date pickers: a popover to place right after the button that opens it, inside a
    flux:dropdown. It reads and sets the picker's `value` and returns the focus to its `trigger` ref. Days
    outside min/max cannot be picked; markers names the renderless method that returns the days of a month to mark,
    otherwise today is marked. year-select adds a year picker for far away dates, and clearable a button to clear
    the day. time-list lists the times of the day, interval minutes apart, beside the weeks, for a picker whose value
    also holds a time. The behaviour lives in the calendar Alpine component (resources/js/calendar.js).
--}}
@props([
    'markers' => null,
    'markerLabel' => null,
    'min' => null,
    'max' => null,
    'yearSelect' => false,
    'clearable' => false,
    'timeList' => false,
    'interval' => 15,
])

<div
    popover="manual"
    role="dialog"
    aria-label="{{ $timeList ? __('Choose date and time') : __('Choose date') }}"
    x-data="calendar({
        today: @js(today()->toDateString()),
        locale: @js(str_replace('_', '-', app()->getLocale())),
        markers: @js($markers),
        min: @js($min),
        max: @js($max),
        timeList: @js($timeList),
        interval: @js((int) $interval),
    })"
    x-on:toggle="toggled($event)"
    x-on:keydown.escape.prevent.stop="close()"
    {{ $attributes->class([$timeList ? 'w-[29rem]' : 'w-80', 'max-h-none! overflow-visible rounded-xl border border-zinc-200 bg-white shadow-lg open:flex max-sm:w-[calc(100vw-2rem)] max-sm:flex-col dark:border-zinc-600 dark:bg-zinc-700']) }}
>
    <div class="flex grow flex-col gap-3 p-4">
        <div class="flex items-center gap-1">
            <span class="grow ps-1 text-sm font-semibold text-zinc-800 dark:text-white" x-text="{{ $yearSelect ? 'monthName' : 'monthLabel' }}"></span>

            @if ($yearSelect)
                @php($years = range($max ? (int) $max : today()->year + 10, $min ? (int) $min : today()->year - 120))

                <flux:select size="sm" class="w-24" x-bind:value="year" x-on:change="showYear($event.target.value)" :aria-label="__('Year')" data-test="calendar-year">
                    @foreach ($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </flux:select>
            @endif

            <flux:button size="sm" variant="ghost" square icon="chevron-left" icon:variant="outline" x-on:click="showMonth(-1)" x-bind:disabled="! canShowMonth(-1)" :aria-label="__('Previous month')" />
            <flux:button size="sm" variant="ghost" square icon="chevron-right" icon:variant="outline" x-on:click="showMonth(1)" x-bind:disabled="! canShowMonth(1)" :aria-label="__('Next month')" />
        </div>

        {{-- The weeks are drawn only while the calendar is open, so a closed one costs nothing. --}}
        <template x-if="open">
            <div class="flex flex-col gap-3">
                <div class="grid grid-cols-7 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400" aria-hidden="true">
                    <template x-for="(weekday, index) in weekdays" :key="index">
                        <span x-text="weekday"></span>
                    </template>
                </div>

                <div class="grid grid-cols-7 gap-0.5" x-on:keydown="navigate($event)">
                    <template x-for="day in days" :key="day.iso">
                        <button
                            type="button"
                            x-on:click="pick(day.iso)"
                            x-bind:data-day="day.iso"
                            x-bind:tabindex="day.iso === focused ? 0 : -1"
                            x-bind:aria-label="day.label"
                            x-bind:aria-pressed="day.iso === selectedDay"
                            x-bind:aria-current="day.isToday ? 'date' : null"
                            x-bind:aria-disabled="day.disabled"
                            x-bind:class="day.inMonth ? 'text-zinc-800 dark:text-white' : 'text-zinc-400 dark:text-zinc-500'"
                            class="group flex h-9.5 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-lg text-sm max-sm:h-11 not-aria-pressed:hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-accent-content aria-[current=date]:font-semibold aria-[current=date]:not-aria-pressed:text-accent-content aria-pressed:bg-accent aria-pressed:font-semibold aria-pressed:text-accent-foreground aria-disabled:cursor-default aria-disabled:text-zinc-300! aria-disabled:hover:bg-transparent dark:not-aria-pressed:hover:bg-white/10 dark:aria-disabled:text-zinc-600!"
                            data-test="calendar-day"
                        >
                            <span x-text="day.number"></span>
                            <span class="size-1 rounded-full" x-bind:class="day.marked && 'bg-accent-content group-aria-pressed:bg-accent-foreground'"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <div class="flex items-center gap-2 border-t border-zinc-100 pt-3 dark:border-white/10">
            @if ($markers && $markerLabel)
                <span class="flex grow items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="size-1.5 rounded-full bg-accent-content"></span>
                    {{ $markerLabel }}
                </span>
            @endif

            @if ($clearable)
                <flux:button size="sm" variant="ghost" x-on:click="pick('')" data-test="calendar-clear">{{ __('Clear') }}</flux:button>
            @endif

            <flux:button size="sm" class="ms-auto" x-on:click="pick(today)" x-bind:disabled="isOutOfRange(today)" data-test="calendar-today">{{ __('Today') }}</flux:button>
        </div>
    </div>

    @if ($timeList)
        {{-- The times run down beside the weeks, within their height, under a header like the month's; on phones they
             run in a row above them, where the footer cannot push them out of sight. Like the weeks, they are drawn only while open. --}}
        <div class="flex w-36 shrink-0 flex-col border-zinc-200 max-sm:order-first max-sm:w-full max-sm:border-b sm:border-s dark:border-white/10">
            <div class="flex h-15 shrink-0 items-center gap-2 border-b border-zinc-200 px-4 text-sm font-medium text-zinc-800 max-sm:hidden dark:border-white/10 dark:text-white">
                <flux:icon.clock class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                {{ __('Time') }}
            </div>

            <div class="relative grow max-sm:h-12">
                <div
                    x-ref="times"
                    role="listbox"
                    aria-label="{{ __('Times') }}"
                    x-on:keydown="navigateTimes($event)"
                    class="absolute inset-0 flex gap-0.5 overflow-auto p-1.5 max-sm:flex-row sm:flex-col"
                >
                    <template x-for="time in (open ? times : [])" :key="time">
                        <button
                            type="button"
                            role="option"
                            x-on:click="pickTime(time)"
                            x-bind:data-time="time"
                            x-bind:aria-selected="time === selectedTime"
                            class="flex shrink-0 cursor-pointer items-center gap-2 rounded-md px-2.5 py-1.5 text-sm tabular-nums text-zinc-800 hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-accent-content aria-selected:bg-zinc-100 aria-selected:font-medium dark:text-white dark:hover:bg-white/10 dark:aria-selected:bg-white/10"
                            data-test="calendar-time"
                        >
                            <flux:icon.check class="size-4 shrink-0" x-bind:class="time !== selectedTime && 'invisible'" />
                            <span x-text="time"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    @endif
</div>
