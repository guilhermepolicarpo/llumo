<?php

namespace App\Enums;

use App\Models\Appointment;
use App\Models\User;
use LogicException;

enum AppointmentAction: string
{
    case Receive = 'receive';
    case UndoReception = 'undo_reception';
    case Start = 'start';
    case ReturnToQueue = 'return_to_queue';
    case Complete = 'complete';
    case MarkAsNoShow = 'mark_as_no_show';
    case Cancel = 'cancel';
    case Reopen = 'reopen';

    /**
     * Get the display label for the action.
     */
    public function label(): string
    {
        return match ($this) {
            self::Receive => __('Receive'),
            self::UndoReception => __('Undo reception'),
            self::Start => __('Attend'),
            self::ReturnToQueue => __('Return to queue'),
            self::Complete => __('Complete'),
            self::MarkAsNoShow => __('Mark as no-show'),
            self::Cancel => __('Cancel appointment'),
            self::Reopen => __('Reopen'),
        };
    }

    /**
     * Get the Heroicon name for the action.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Receive => 'arrow-right-end-on-rectangle',
            self::UndoReception, self::ReturnToQueue, self::Reopen => 'arrow-uturn-left',
            self::Start => 'play',
            self::Complete => 'check-circle',
            self::MarkAsNoShow => 'user-minus',
            self::Cancel => 'x-circle',
        };
    }

    /**
     * Determine whether the action is the main next step, shown as a button on the appointment row.
     */
    public function isPrimary(): bool
    {
        return in_array($this, [self::Receive, self::Start, self::Complete], true);
    }

    /**
     * Determine whether the action must be confirmed before it is performed.
     */
    public function needsConfirmation(): bool
    {
        return in_array($this, [self::MarkAsNoShow, self::Cancel], true);
    }

    /**
     * Get the confirmation message shown before performing the action on the described appointment.
     */
    public function confirmation(string $appointmentDescription): string
    {
        return match ($this) {
            self::MarkAsNoShow => __('Are you sure you want to mark the appointment of :description as a no-show?', ['description' => $appointmentDescription]),
            self::Cancel => __('Are you sure you want to cancel the appointment of :description?', ['description' => $appointmentDescription]),
            default => throw new LogicException("The [{$this->value}] action does not need confirmation."),
        };
    }

    /**
     * Get the statuses an appointment may be in for the action to apply.
     *
     * @return array<int, AppointmentStatus>
     */
    public function fromStatuses(): array
    {
        return match ($this) {
            self::Receive, self::MarkAsNoShow, self::Cancel => [AppointmentStatus::Scheduled],
            self::UndoReception, self::Start => [AppointmentStatus::Waiting],
            self::ReturnToQueue, self::Complete => [AppointmentStatus::InProgress],
            self::Reopen => [AppointmentStatus::NoShow, AppointmentStatus::Canceled],
        };
    }

    /**
     * Get the status an appointment moves to after the action.
     */
    public function toStatus(): AppointmentStatus
    {
        return match ($this) {
            self::Receive, self::ReturnToQueue => AppointmentStatus::Waiting,
            self::UndoReception, self::Reopen => AppointmentStatus::Scheduled,
            self::Start => AppointmentStatus::InProgress,
            self::Complete => AppointmentStatus::Completed,
            self::MarkAsNoShow => AppointmentStatus::NoShow,
            self::Cancel => AppointmentStatus::Canceled,
        };
    }

    /**
     * Get the attributes the action writes to the appointment, including its new status.
     *
     * @return array<string, mixed>
     */
    public function attributes(User $user): array
    {
        return ['status' => $this->toStatus()] + match ($this) {
            self::Receive => ['received_at' => now()],
            self::UndoReception => ['received_at' => null],
            self::Start => ['started_at' => now(), 'attendant_id' => $user->id],
            self::ReturnToQueue => ['started_at' => null, 'attendant_id' => null],
            self::Complete => ['finished_at' => now()],
            self::MarkAsNoShow, self::Cancel, self::Reopen => [],
        };
    }

    /**
     * Determine whether the action can be performed on the given appointment.
     */
    public function isAvailableFor(Appointment $appointment): bool
    {
        return in_array($appointment->status, $this->fromStatuses(), true) && match ($this) {
            self::Receive => $appointment->scheduled_on->isToday(),
            default => true,
        };
    }

    /**
     * Get the actions that can be performed on the given appointment.
     *
     * @return array<int, self>
     */
    public static function availableFor(Appointment $appointment): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $action): bool => $action->isAvailableFor($appointment),
        ));
    }
}
