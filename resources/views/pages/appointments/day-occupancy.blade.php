@props(['occupancy', 'testPrefix'])

@if ($occupancy['remaining'] === 0)
    <flux:callout
        variant="warning"
        icon="exclamation-triangle"
        icon:variant="outline"
        :heading="__('Limit reached: :count of :limit :type appointments on :date.', $occupancy)"
        data-test="{{ $testPrefix }}-full"
    />
@else
    <flux:text class="text-sm" data-test="{{ $testPrefix }}-occupancy">
        {{ trans_choice('{0} No :type appointments on :date|{1} 1 :type appointment on :date|[2,*] :count :type appointments on :date', $occupancy['count'], $occupancy) }}
        @if ($occupancy['remaining'] !== null)
            · <span class="font-medium whitespace-nowrap text-green-700 dark:text-green-400">{{ trans_choice('{1} 1 spot left|[2,*] :count spots left', $occupancy['remaining']) }}</span>
        @endif
    </flux:text>
@endif
