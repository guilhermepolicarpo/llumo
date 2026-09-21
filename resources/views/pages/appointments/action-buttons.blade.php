{{-- editAs places the Edit entry: 'menu' (dropdown item), 'button' (visible button), or null (hidden). --}}
@props(['appointment', 'size' => 'base', 'editAs' => 'menu'])

@use('App\Enums\AppointmentAction')
@use('App\Enums\AppointmentStatus')

@php
    [$primaryActions, $secondaryActions] = collect(AppointmentAction::availableFor($appointment))->partition(fn (AppointmentAction $action) => $action->isPrimary());
    $isEditable = $appointment->status->isEditable();
    $usesRecord = $appointment->usesRecord();

    if ($usesRecord) {
        $primaryActions = $primaryActions->reject(fn (AppointmentAction $action) => in_array($action, [AppointmentAction::Start, AppointmentAction::Complete], true));
    }

    $isAttendable = $usesRecord && $appointment->status->isAttendable();
@endphp

@if ($isAttendable || $primaryActions->isNotEmpty() || $secondaryActions->isNotEmpty() || $isEditable)
<div {{ $attributes->class('flex items-center justify-end gap-2') }}>
    @if ($isEditable && $editAs === 'button')
        <flux:button
            :size="$size"
            icon="pencil"
            :href="route('appointments.edit', ['appointment' => $appointment])"
            wire:navigate
            data-test="appointment-edit-button"
            >
            {{ __('Edit') }}
        </flux:button>
    @endif

    @if ($isAttendable)
        @php($isWaiting = $appointment->status === AppointmentStatus::Waiting)
        <flux:button
            :size="$size"
            :icon="$isWaiting ? 'play' : 'clipboard-document-list'"
            :href="route('appointments.attend', ['appointment' => $appointment])"
            wire:navigate
            data-test="appointment-attend-button"
            >
            {{ $isWaiting ? AppointmentAction::Start->label() : __('Continue attending') }}
        </flux:button>
    @endif

    @foreach ($primaryActions as $action)
        <flux:button
            :size="$size"
            :icon="$action->icon()"
            wire:click="perform({{ $appointment->id }}, '{{ $action->value }}')"
            wire:loading.attr="disabled"
            data-test="appointment-action-{{ $action->value }}"
            >
            {{ $action->label() }}
        </flux:button>
    @endforeach

    @if ($secondaryActions->isNotEmpty() || $isEditable)
        <flux:dropdown position="bottom" align="end">
            <flux:button variant="ghost" :size="$size" icon="ellipsis-horizontal" :aria-label="__('More actions')" data-test="appointment-actions-trigger" />
            <flux:menu>
                <x-pages::appointments.action-menu-items :appointment="$appointment" :actions="$secondaryActions" />

                @if ($isEditable)
                    @if ($secondaryActions->isNotEmpty())
                        <flux:menu.separator />
                    @endif

                    @if ($editAs === 'menu')
                        <flux:menu.item as="a" href="{{ route('appointments.edit', ['appointment' => $appointment]) }}" wire:navigate icon="pencil" data-test="appointment-edit-menu-item">
                            {{ __('Edit') }}
                        </flux:menu.item>
                    @endif

                    <flux:menu.item
                        variant="danger"
                        icon="trash"
                        wire:click="$dispatch('confirm-delete-appointment', { appointmentId: {{ $appointment->id }}, appointmentDescription: @js($appointment->description) })"
                        data-test="appointment-delete-menu-item"
                        >
                        {{ __('Delete') }}
                    </flux:menu.item>
                @endif
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
@endif
