{{-- Lists what was recorded in an appointment, skipping the fields left blank. --}}
@props(['record'])

<dl {{ $attributes->class('grid gap-x-6 gap-y-4 text-sm sm:grid-cols-[12rem_1fr]') }}>
    @foreach (array_filter([
        __('Mentor') => $record->mentor?->name,
        __('Fluidic remedies') => $record->fluidicRemedies->pluck('name')->implode(', '),
        __('How to take the fluidic remedy') => $record->fluid_instructions,
        __('Guidances') => $record->guidance_summary,
        __('Passes') => $record->pass_summary,
        __('Infiltration') => $record->infiltration_summary,
        __('Return date') => $record->return_on?->format('d/m/Y'),
        __('Observations') => $record->observations,
    ], fn ($value) => filled($value)) as $label => $value)
        <dt class="text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
        <dd class="whitespace-pre-line text-zinc-900 dark:text-white">{{ $value }}</dd>
    @endforeach
</dl>
