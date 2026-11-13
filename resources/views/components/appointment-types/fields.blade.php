{{-- The appointment type form fields, bound to the AppointmentTypeForm in the component's $form property. --}}
<flux:input wire:model="form.name" :label="__('Name')" :placeholder="__('E.g. Fraternal assistance')" required autofocus data-test="appointment-type-name-input" />

<flux:input
    type="number"
    min="1"
    max="999"
    :label="__('Daily appointment limit')"
    :placeholder="__('E.g. 20')"
    :description:trailing="__('Leave blank for no limit.')"
    wire:model="form.dailyLimit"
    data-test="appointment-type-daily-limit-input"
/>

<flux:checkbox.group
    wire:model="form.weekdays"
    variant="pills"
    :label="__('Days it takes place')"
    :description="__('A warning is shown when an appointment of this type falls on another day. Leave all unselected to allow any day.')"
    data-test="appointment-type-weekdays-checkbox-group"
>
    @foreach (\App\Enums\Weekday::options() as $weekdayOption)
        <flux:checkbox :value="$weekdayOption['value']" :label="$weekdayOption['label']" data-test="appointment-type-weekday-{{ $weekdayOption['value'] }}" />
    @endforeach
</flux:checkbox.group>

{{-- Each chosen weekday gets its time, shown and hidden in the browser as the days are checked, without a request. --}}
<flux:field x-show="$wire.form.weekdays.length > 0" data-test="appointment-type-weekday-times">
    <flux:label :badge="__('Optional')">{{ __('Time') }}</flux:label>
    <flux:description>{{ __('The time the assisted person must be at the center on each day.') }}</flux:description>

    <div class="grid grid-cols-2 gap-3">
        @foreach (\App\Enums\Weekday::options() as $weekdayOption)
            <div x-show="$wire.form.weekdays.includes(@js($weekdayOption['value']))">
                <flux:input.group>
                    <flux:input.group.prefix class="w-14 justify-center">{{ $weekdayOption['label'] }}</flux:input.group.prefix>
                    <flux:input
                        type="time"
                        wire:model="form.weekdayTimes.{{ $weekdayOption['value'] }}"
                        :aria-label="$weekdayOption['label']"
                        data-test="appointment-type-weekday-time-{{ $weekdayOption['value'] }}"
                    />
                </flux:input.group>
            </div>
        @endforeach
    </div>

    <flux:error name="form.weekdayTimes.*" />
</flux:field>

<flux:switch
    wire:model="form.requiresRecord"
    :label="__('Fill in a record during the appointment')"
    :description="__('Opens the appointment record screen when the assisted person is attended.')"
    align="left"
    data-test="appointment-type-requires-record-switch"
/>
