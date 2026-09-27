{{-- The assisted person's personal fields, bound to the InteractsWithAssistedPersonForm properties. --}}
<flux:fieldset>
    <flux:input wire:model="name" :label="__('Name')" required autofocus :placeholder="__('E.g. John Doe')" data-test="assisted-person-name-input" />
    <flux:input type="email" wire:model="email" :label="__('Email')" :placeholder="__('E.g. john.doe@example.com')" data-test="assisted-person-email-input" />

    <div class="grid gap-6 sm:grid-cols-2">
        <flux:input type="date" wire:model="birthDate" :label="__('Birth date')" max="{{ today()->toDateString() }}" data-test="assisted-person-birth-date-input" />
        <flux:input wire:model="phone" :label="__('Phone')" placeholder="(00) 00000-0000" inputmode="numeric" mask="(99) 99999-9999" data-test="assisted-person-phone-input" />
    </div>
</flux:fieldset>
