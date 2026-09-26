{{-- editAs places the Edit entry: 'menu' (dropdown item), 'button' (visible button), or null (hidden). --}}
{{-- menuFirst moves the dropdown to the start of the row, pushing the buttons to the opposite edge. --}}
{{-- emphasizeMain paints the next-step button (attend, receive, start, complete) as primary; the attend button is always primary. --}}
@props(['appointment', 'size' => 'base', 'editAs' => 'menu', 'menuFirst' => false, 'emphasizeMain' => false])

@use('App\Enums\AppointmentAction')
@use('App\Enums\AppointmentStatus')

@php
    $availableActions = collect(AppointmentAction::availableFor($appointment));
    [$primaryActions, $secondaryActions] = $availableActions->partition(fn (AppointmentAction $action) => $action->isPrimary());
    $startsAttendance = $availableActions->contains(AppointmentAction::Start);
    $isEditable = $appointment->status->isEditable();
    $usesRecord = $appointment->usesRecord();

    if ($usesRecord) {
        $primaryActions = $primaryActions->reject(fn (AppointmentAction $action) => in_array($action, [AppointmentAction::Start, AppointmentAction::Complete], true));
    }

    $isAttendable = $usesRecord && ($startsAttendance || $appointment->status === AppointmentStatus::InProgress);
    $mainVariant = $emphasizeMain ? 'primary' : 'outline';
@endphp

@if ($isAttendable || $primaryActions->isNotEmpty() || $secondaryActions->isNotEmpty() || $isEditable)
<div {{ $attributes->class('flex items-center justify-end gap-2') }}>
    @if ($isEditable && $editAs === 'button')
        <flux:button
            :size="$size"
            icon="pencil"
            icon:variant="outline"
            :href="route('appointments.edit', ['appointment' => $appointment])"
            wire:navigate
            data-test="appointment-edit-button"
            >
            {{ __('Edit') }}
        </flux:button>
    @endif

    @if ($isAttendable)
        <flux:button
            :variant="$startsAttendance ? 'primary' : $mainVariant"
            :size="$size"
            :icon="$startsAttendance ? 'play' : 'clipboard-document-list'"
            icon:variant="outline"
            :href="route('appointments.attend', ['appointment' => $appointment])"
            wire:navigate
            data-test="appointment-attend-button"
            >
            {{ $startsAttendance ? AppointmentAction::Start->label() : __('Continue') }}
        </flux:button>
    @endif

    @foreach ($primaryActions as $action)
        <flux:button
            :variant="$action === AppointmentAction::Start ? 'primary' : $mainVariant"
            :size="$size"
            :icon="$action->icon()"
            icon:variant="outline"
            wire:click="perform({{ $appointment->id }}, '{{ $action->value }}')"
            wire:loading.attr="disabled"
            data-test="appointment-action-{{ $action->value }}"
            >
            {{ $action->label() }}
        </flux:button>
    @endforeach

    @if ($secondaryActions->isNotEmpty() || $isEditable)
        <flux:dropdown position="bottom" align="end" :class="$menuFirst ? 'order-first me-auto' : null">
            <flux:button variant="ghost" :size="$size" icon="ellipsis-horizontal" icon:variant="outline" :aria-label="__('More actions')" data-test="appointment-actions-trigger" />
            <flux:menu>
                <x-pages::appointments.action-menu-items :appointment="$appointment" :actions="$secondaryActions" />

                @if ($isEditable)
                    @if ($secondaryActions->isNotEmpty())
                        <flux:menu.separator />
                    @endif

                    @if ($editAs === 'menu')
                        <flux:menu.item as="a" href="{{ route('appointments.edit', ['appointment' => $appointment]) }}" wire:navigate icon="pencil" icon:variant="outline" data-test="appointment-edit-menu-item">
                            {{ __('Edit') }}
                        </flux:menu.item>
                    @endif

                    <flux:menu.item
                        variant="danger"
                        icon="trash"
                        icon:variant="outline"
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
