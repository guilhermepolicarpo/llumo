@props(['states', 'testPrefix'])

<div class="space-y-6">
    <div>
        <flux:heading>{{ __('Address') }}</flux:heading>
        <flux:subheading>{{ __('Fill in the postal code to complete the address automatically') }}</flux:subheading>
    </div>

    <div class="grid gap-6 sm:grid-cols-6">
        <div class="sm:col-span-2">
            <flux:input
                wire:model.live.blur="postalCode"
                :label="__('Postal code')"
                placeholder="00000-000"
                inputmode="numeric"
                mask="99999-999"
                data-test="{{ $testPrefix }}-postal-code-input"
            />

            <flux:text wire:loading wire:target="postalCode" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Looking up address...') }}
            </flux:text>
        </div>

        <div class="sm:col-span-4">
            <flux:input wire:model="street" :label="__('Street')" data-test="{{ $testPrefix }}-street-input" />
        </div>

        <div class="sm:col-span-2">
            <flux:input wire:model="number" :label="__('Number')" data-test="{{ $testPrefix }}-number-input" />
        </div>

        <div class="sm:col-span-4">
            <flux:input wire:model="complement" :label="__('Complement')" data-test="{{ $testPrefix }}-complement-input" />
        </div>

        <div class="sm:col-span-2">
            <flux:input wire:model="district" :label="__('District')" data-test="{{ $testPrefix }}-district-input" />
        </div>

        <div class="sm:col-span-2">
            <flux:input wire:model="city" :label="__('City')" data-test="{{ $testPrefix }}-city-input" />
        </div>

        <div class="sm:col-span-2">
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
    </div>
</div>
