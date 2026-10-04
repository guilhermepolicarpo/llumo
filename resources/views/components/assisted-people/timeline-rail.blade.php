@blaze(memo: true)

{{-- The history timeline's column beside an entry: a line from the previous entry, the entry's status dot, and a line to the next one. --}}
{{-- The offset lines the dot up with the day in the date block: "card" for a full entry, "row" for a missed one, "upcoming" for one ahead (dashed). --}}
@props([
    'status',
    'offset' => 'card',
    'first' => false,
    'last' => false,
])

@php
    $isUpcoming = $offset === 'upcoming';
    $lineClasses = $isUpcoming ? 'w-0 border-s border-dashed border-zinc-300 dark:border-white/20' : 'w-px bg-zinc-200 dark:bg-white/10';
    $dotClasses = $isUpcoming && $status === App\Enums\AppointmentStatus::Scheduled
        ? 'border-2 border-zinc-400 bg-white dark:bg-zinc-900'
        : match ($status->color()) {
            'green' => 'bg-green-600',
            'red' => 'bg-red-400',
            'amber' => 'bg-amber-400',
            'blue' => 'bg-blue-500',
            default => 'bg-zinc-300 dark:bg-zinc-600',
        };
@endphp

<div aria-hidden="true" {{ $attributes->class('flex w-3 shrink-0 flex-col items-center') }}>
    <span @class([
        'shrink-0',
        $lineClasses,
        'h-[23px]' => $offset === 'card',
        'h-3.5' => $offset === 'row',
        'h-[26px]' => $isUpcoming,
        'invisible' => $first,
    ])></span>
    <span @class(['shrink-0 rounded-full', $offset === 'card' ? 'size-3' : 'size-2.5', $dotClasses])></span>
    <span @class(['grow', $lineClasses, 'invisible' => $last])></span>
</div>
