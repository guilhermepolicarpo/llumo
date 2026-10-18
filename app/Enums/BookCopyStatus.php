<?php

namespace App\Enums;

/**
 * Whether a copy is still part of the collection. Whether an active copy is lent out is not stored here:
 * it follows from the copy having an open loan.
 */
enum BookCopyStatus: string
{
    case Active = 'active';
    case Lost = 'lost';
    case Withdrawn = 'withdrawn';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Lost => __('Lost'),
            self::Withdrawn => __('Withdrawn'),
        };
    }

    /**
     * Get the badge color for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Lost => 'red',
            self::Withdrawn => 'zinc',
        };
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
