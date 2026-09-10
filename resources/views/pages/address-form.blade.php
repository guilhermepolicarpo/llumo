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
        class="max-w-3xs"
    />
    
    
    <div class="grid gap-6 sm:grid-cols-6 max-w-2xl">
        <div class="sm:col-span-2">
            <flux:input wire:model="street" :label="__('Street')" data-test="{{ $testPrefix }}-street-input" />
        </div>

        <div class="sm:col-span-1">
            <flux:input wire:model="number" :label="__('Number')" data-test="{{ $testPrefix }}-number-input" />
        </div>

        <div class="sm:col-span-2">
            <flux:input wire:model="complement" :label="__('Complement')" data-test="{{ $testPrefix }}-complement-input" />
        </div>

        <div class="sm:col-span-2">
            <flux:input wire:model="district" :label="__('District')" data-test="{{ $testPrefix }}-district-input" />
        </div>

        <div class="sm:col-span-2">
            <flux:input wire:model="city" :label="__('City')" data-test="{{ $testPrefix }}-city-input" />
        </div>

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
