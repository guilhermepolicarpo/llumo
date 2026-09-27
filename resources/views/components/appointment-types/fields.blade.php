{{-- The appointment type form fields, bound to the InteractsWithAppointmentTypeForm properties. --}}
<flux:input wire:model="name" :label="__('Name')" :placeholder="__('Fraternal assistance')" required autofocus data-test="appointment-type-name-input" />

<flux:switch
    wire:model="requiresRecord"
    :label="__('Fill in a record during the appointment')"
    :description="__('Opens the appointment record screen when the assisted person is attended.')"
    align="left"
    data-test="appointment-type-requires-record-switch"
/>

<flux:input
    type="number"
    min="1"
    max="999"
    wire:model="dailyLimit"
    :label="__('Daily appointment limit')"
    :description="__('Leave blank for no limit.')"
    data-test="appointment-type-daily-limit-input"
/>
