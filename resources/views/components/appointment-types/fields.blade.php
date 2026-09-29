{{-- The appointment type form fields, bound to the InteractsWithAppointmentTypeForm properties. --}}
<flux:input wire:model="name" :label="__('Name')" :placeholder="__('E.g. Fraternal assistance')" required autofocus data-test="appointment-type-name-input" />

<flux:input
    type="number"
    min="1"
    max="999"
    :label="__('Daily appointment limit')"
    :placeholder="__('E.g. 20')"
    :description:trailing="__('Leave blank for no limit.')"
    wire:model="dailyLimit"
    data-test="appointment-type-daily-limit-input"
/>

<flux:checkbox.group
    wire:model="weekdays"
    variant="pills"
    :label="__('Days it takes place')"
    :description="__('A warning is shown when an appointment of this type falls on another day. Leave all unselected to allow any day.')"
    data-test="appointment-type-weekdays-checkbox-group"
>
    @foreach (\App\Enums\Weekday::options() as $weekdayOption)
        <flux:checkbox :value="$weekdayOption['value']" :label="$weekdayOption['label']" data-test="appointment-type-weekday-{{ $weekdayOption['value'] }}" />
    @endforeach
</flux:checkbox.group>

<flux:switch
    wire:model="requiresRecord"
    :label="__('Fill in a record during the appointment')"
    :description="__('Opens the appointment record screen when the assisted person is attended.')"
    align="left"
    data-test="appointment-type-requires-record-switch"
/>
