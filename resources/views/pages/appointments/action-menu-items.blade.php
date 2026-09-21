@props(['appointment', 'actions'])

@foreach ($actions as $action)
    @if ($action->needsConfirmation())
        <flux:menu.item
            :icon="$action->icon()"
            wire:click="$dispatch('confirm-appointment-action', { appointmentId: {{ $appointment->id }}, action: '{{ $action->value }}', appointmentDescription: {{ \Illuminate\Support\Js::from($appointment->description) }} })"
            data-test="appointment-action-{{ $action->value }}"
            >
            {{ $action->label() }}
        </flux:menu.item>
    @else
        <flux:menu.item
            :icon="$action->icon()"
            wire:click="perform({{ $appointment->id }}, '{{ $action->value }}')"
            data-test="appointment-action-{{ $action->value }}"
            >
            {{ $action->label() }}
        </flux:menu.item>
    @endif
@endforeach
