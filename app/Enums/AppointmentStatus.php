<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Canceled = 'canceled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::Waiting => __('Waiting'),
            self::InProgress => __('In progress'),
            self::Completed => __('Completed'),
            self::NoShow => __('No-show'),
            self::Canceled => __('Canceled'),
        };
    }

    /**
     * Get the full description of the status, shown as a tooltip.
     */
    public function description(): string
    {
        return match ($this) {
            self::Scheduled => __('Appointment scheduled, awaiting arrival'),
            self::Waiting => __('Arrived and waiting to be attended'),
            self::InProgress => __('Appointment in progress'),
            self::Completed => __('Appointment completed'),
            self::NoShow => __('Did not attend the appointment'),
            self::Canceled => __('Appointment canceled'),
        };
    }

    /**
     * Get the badge color for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Scheduled, self::Canceled => 'zinc',
            self::Waiting => 'amber',
            self::InProgress => 'blue',
            self::Completed => 'green',
            self::NoShow => 'red',
        };
    }

    /**
     * Determine whether an appointment in this status can still be edited or deleted.
     */
    public function isEditable(): bool
    {
        return $this === self::Scheduled;
    }

    /**
     * Determine whether an appointment in this status can be attended, opening its record.
     */
    public function isAttendable(): bool
    {
        return in_array($this, [self::Waiting, self::InProgress], true);
    }

    /**
     * Determine whether the record of an appointment in this status can be filled in or corrected.
     */
    public function allowsRecordEditing(): bool
    {
        return in_array($this, [self::InProgress, self::Completed], true);
    }

    /**
     * Get the statuses as select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
