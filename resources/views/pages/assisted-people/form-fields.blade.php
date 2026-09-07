<div class="space-y-6">
    <flux:fieldset>
        <flux:legend>{{ __('Personal details') }}</flux:legend>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <flux:input wire:model="form.name" :label="__('Full name')" required autofocus data-test="assisted-person-name" />
            </div>

            <flux:input type="date" wire:model="form.birth_date" :label="__('Date of birth')" required data-test="assisted-person-birth-date" />

            <flux:input wire:model="form.phone" :label="__('Phone')" required mask="(99) 99999-9999" placeholder="(00) 00000-0000" data-test="assisted-person-phone" />

            <div class="sm:col-span-2">
                <flux:input type="email" wire:model="form.email" :label="__('Email')" data-test="assisted-person-email" />
            </div>
        </div>
    </flux:fieldset>

    <flux:fieldset>
        <flux:legend>{{ __('Address') }}</flux:legend>

        <div class="grid gap-4 sm:grid-cols-6">
            <div class="sm:col-span-2">
                <flux:input
                    wire:model.live.debounce.500ms="form.postal_code"
                    :label="__('Postal code')"
                    mask="99999-999"
                    placeholder="00000-000"
                />
            </div>

            <div class="sm:col-span-4">
                <flux:input wire:model="form.street" :label="__('Street')" data-test="assisted-person-street" />
            </div>

            <div class="sm:col-span-2">
                <flux:input wire:model="form.number" :label="__('Number')" data-test="assisted-person-number" />
            </div>

            <div class="sm:col-span-4">
                <flux:input wire:model="form.complement" :label="__('Complement')" data-test="assisted-person-complement" />
            </div>

            <div class="sm:col-span-3">
                <flux:input wire:model="form.district" :label="__('District')" data-test="assisted-person-district" />
            </div>

            <div class="sm:col-span-3">
                <flux:input wire:model="form.city" :label="__('City')" data-test="assisted-person-city" />
            </div>

            <div class="sm:col-span-3">
                <flux:select wire:model="form.state" :label="__('State')" :placeholder="__('Select a state')" data-test="assisted-person-state">
                    @foreach (\App\Enums\BrazilianState::options() as $option)
                        <flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </flux:fieldset>
</div>
