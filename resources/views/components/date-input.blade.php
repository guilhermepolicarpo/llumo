{{--
    A form field for a single date, bound with wire:model (or x-model, inside an Alpine loop) to a 'Y-m-d' string (empty for no date): the day is typed
    as dd/mm/yyyy or picked in the shared month calendar opened from the trailing button, and on touch devices a
    transparent native date input over the field opens the system picker instead. min/max ('Y-m-d') bound the days
    allowed; year-select adds a year picker to the calendar for far away dates, such as a birth date.

    With time, the field also takes a time: the value becomes 'Y-m-d H:i' once one is chosen, it is typed as
    dd/mm/yyyy hh:mm, and the calendar lists the times of the day, interval minutes apart, beside the weeks. Touch
    devices get the system's date and time picker, where midnight stands for no time, as it does in the record.

    Any other attribute, such as required or data-test, goes to the text input. The behaviour lives in the dateInput
    Alpine component (resources/js/date-input.js).
--}}
@props([
    'label' => null,
    'min' => null,
    'max' => null,
    'yearSelect' => false,
    'time' => false,
    'interval' => 15,
])

@php($model = $attributes->wire('model')->value())

<div
    x-data="dateInput({ min: @js($min), max: @js($max), withTime: @js((bool) $time) })"
    x-modelable="value"
    {{ $attributes->whereStartsWith(['wire:model', 'x-model']) }}
    {{ $attributes->only('class') }}
>
    <flux:field>
        @if ($label)
            <flux:label>{{ $label }}</flux:label>
        @endif

        <div class="relative">
            <flux:input
                :attributes="$attributes->whereDoesntStartWith(['wire:model', 'x-model'])->except('class')"
                x-model="text"
                x-ref="trigger"
                x-on:blur="commit()"
                x-on:keydown.enter="commit()"
                :mask="$time ? '99/99/9999 99:99' : '99/99/9999'"
                inputmode="numeric"
                :placeholder="$time ? __('dd/mm/yyyy hh:mm') : __('dd/mm/yyyy')"
                :name="$model"
            >
                <x-slot:iconTrailing>
                    <flux:dropdown position="bottom" align="end">
                        <flux:button
                            size="sm"
                            variant="subtle"
                            square
                            icon="calendar-days"
                            icon:variant="outline"
                            inset="left right"
                            :aria-label="$time ? __('Choose date and time') : __('Choose date')"
                            data-test="date-input-calendar-button"
                        />

                        <x-calendar :min="$min" :max="$max" :year-select="$yearSelect" :time-list="(bool) $time" :interval="$interval" clearable />
                    </flux:dropdown>
                </x-slot:iconTrailing>
            </flux:input>

            {{-- On touch devices the system date picker serves better than the calendar, so this native input covers the field. --}}
            <input
                type="{{ $time ? 'datetime-local' : 'date' }}"
                x-model="{{ $time ? 'nativeValue' : 'value' }}"
                @if ($min) min="{{ $min }}{{ $time ? 'T00:00' : '' }}" @endif
                @if ($max) max="{{ $max }}{{ $time ? 'T23:59' : '' }}" @endif
                aria-label="{{ $label ?? __('Date') }}"
                class="absolute inset-0 hidden size-full cursor-pointer opacity-0 pointer-coarse:block"
            >
        </div>

        <flux:error :name="$model" />
    </flux:field>
</div>
