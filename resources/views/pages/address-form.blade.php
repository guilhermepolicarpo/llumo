@props(['states', 'testPrefix'])

<flux:fieldset>
    <flux:legend>{{ __('Address') }}</flux:legend>
    <flux:description>{{ __('Fill in the postal code to complete the address automatically') }}</flux:description>

    <flux:input
        wire:model.live.blur="postalCode"
        :label="__('Postal code')"
        placeholder="00000-000"
        inputmode="numeric"
        mask="99999-999"
        data-test="{{ $testPrefix }}-postal-code-input"
        class="max-w-50"
    />

    <flux:input wire:model="street" :label="__('Street')" :placeholder="__('Main St')" data-test="{{ $testPrefix }}-street-input" />

    <div class="mb-3 grid gap-6 sm:grid-cols-2">
        <flux:input wire:model="number" :label="__('Number')" placeholder="123" data-test="{{ $testPrefix }}-number-input" />
        <flux:input wire:model="complement" :label="__('Complement')" :placeholder="__('Apt 4B')" data-test="{{ $testPrefix }}-complement-input" />
    </div>

    <flux:input wire:model="district" :label="__('District')" :placeholder="__('Downtown')" data-test="{{ $testPrefix }}-district-input" />

    <div class="grid gap-6 sm:grid-cols-2">
        <flux:input wire:model="city" :label="__('City')" :placeholder="__('New York')" data-test="{{ $testPrefix }}-city-input" />

        <flux:select
            wire:model="state"
            :label="__('State')"
            :placeholder="__('UF')"
            data-test="{{ $testPrefix }}-state-select"
        >
            @foreach ($states as $stateOption)
                <flux:select.option :value="$stateOption['value']">
                    {{ $stateOption['label'] }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </div>
</flux:fieldset>
